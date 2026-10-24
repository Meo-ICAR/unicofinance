<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Pratica;
use App\Models\User;
use App\Services\Istruttoria\PresaInCarico;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Employee;
use Unico\Core\Models\PraticaStato;

class PresaInCaricoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function istruttore(string $nome = 'Istruttore Prova', bool $conRuolo = true): Employee
    {
        $utente = User::factory()->create(['role' => 'employee']);

        return Employee::query()->create(['name' => $nome, 'user_id' => $utente->getKey(), 'is_active' => true, 'employee_roles' => $conRuolo ? ['istruttore'] : ['dipendente']]);
    }

    private function inIstruttoria(?Employee $assegnata = null): Pratica
    {
        $stato = PraticaStato::query()->firstOrCreate(['codice' => 'INVIO IN ISTRUTTORIA', 'tipo_finanziamento' => ''], ['name' => 'INVIO IN ISTRUTTORIA', 'ordine' => 0, 'colore' => 'gray']);

        return Pratica::query()->create(['codice_pratica' => 'PIC-'.uniqid(), 'cognome_cliente' => 'Rossi', 'pratica_stato_id' => $stato->getKey(), 'employee_id' => $assegnata?->getKey(), 'upload_at' => now()]);
    }

    private function apriComeAdmin(Employee $istruttore, Pratica $pratica): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::query()->findOrFail($istruttore->user_id));

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])->assertOk();
    }

    public function test_the_instructor_opening_a_waiting_practice_takes_it_in_charge(): void
    {
        $istruttore = $this->istruttore();
        $pratica = $this->inIstruttoria($istruttore);

        $this->apriComeAdmin($istruttore, $pratica);

        $pratica->refresh();
        $this->assertSame('PRESA IN CARICO', $pratica->praticaStato->codice);
        $this->assertSame($istruttore->getKey(), $pratica->employee_id);
        $this->assertTrue($pratica->presa_in_carico_at->isToday());
        $storico = $pratica->praticaStatusHistory()->firstOrFail();
        $this->assertSame('INVIO IN ISTRUTTORIA', $storico->status_from);
        $this->assertSame('PRESA IN CARICO', $storico->status_to);
        $this->assertSame('istruttoria', $storico->source);
    }

    public function test_a_free_practice_goes_to_whoever_opens_it(): void
    {
        $istruttore = $this->istruttore();
        $pratica = $this->inIstruttoria();

        $this->apriComeAdmin($istruttore, $pratica);

        $this->assertSame($istruttore->getKey(), $pratica->fresh()->employee_id);
        $this->assertSame('PRESA IN CARICO', $pratica->fresh()->praticaStato->codice);
    }

    public function test_opening_does_nothing_if_assigned_to_another_instructor_or_if_the_user_is_not_an_instructor(): void
    {
        $assegnatario = $this->istruttore('Assegnatario');
        $altro = $this->istruttore('Altro istruttore');
        $dipendente = $this->istruttore('Solo dipendente', false);
        $pratica = $this->inIstruttoria($assegnatario);

        $this->apriComeAdmin($altro, $pratica);
        $this->apriComeAdmin($dipendente, $pratica);

        $pratica->refresh();
        $this->assertSame('INVIO IN ISTRUTTORIA', $pratica->praticaStato->codice);
        $this->assertSame($assegnatario->getKey(), $pratica->employee_id);
        $this->assertNull($pratica->presa_in_carico_at);
    }

    public function test_an_already_taken_practice_keeps_its_state_when_opened_again(): void
    {
        $istruttore = $this->istruttore();
        $pratica = $this->inIstruttoria($istruttore);

        $this->apriComeAdmin($istruttore, $pratica);
        $this->apriComeAdmin($istruttore, $pratica);

        $this->assertSame(1, $pratica->fresh()->praticaStatusHistory()->count());
    }

    public function test_the_producer_can_edit_only_before_the_presa_in_carico(): void
    {
        $servizio = app(PresaInCarico::class);
        $istruttore = $this->istruttore();
        $attesa = $this->inIstruttoria($istruttore);
        $presa = $this->inIstruttoria($istruttore);
        $servizio->prendi($presa, $istruttore, 'istruttoria');

        $this->assertTrue($servizio->modificabileDalProduttore($attesa->fresh(['praticaStato'])));
        $this->assertFalse($servizio->modificabileDalProduttore($presa->fresh(['praticaStato'])));
        $this->assertSame([$presa->getKey()], PresaInCarico::bloccateAlProduttore(Pratica::query())->pluck('id')->all());
    }
}
