<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica as AgentiEditPratica;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPratiche;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

class RinunciaClienteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    public function test_the_producer_waives_the_client_and_the_instructor_sees_it_until_acknowledged(): void
    {
        Filament::setCurrentPanel('agenti');
        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = Fornitore::query()->create(['name' => 'Produttore', 'piva' => '996'.random_int(10000000, 99999999), 'user_id' => $agent->id, 'is_active' => true]);
        $cessione = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $rinuncia = PraticaStato::query()->where('codice', 'RINUNCIA CLIENTE')->where('tipo_finanziamento', 'Cessione')->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZRIN-'.Str::upper(Str::random(4)), 'fornitori_id' => $fornitore->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($fornitore, $pratica);

        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);
        $this->actingAs($agent);

        $istruttoreUser = User::factory()->create(['role' => 'super_admin']);
        $istruttore = Employee::query()->create(['name' => 'Istruttore', 'user_id' => $istruttoreUser->id]);
        $pratica->update(['employee_id' => $istruttore->getKey()]);

        Livewire::test(ListPratiche::class)
            ->callTableAction('rinunciaCliente', $pratica, ['nota' => 'ha cambiato idea']);

        $pratica->refresh();
        $this->assertSame($rinuncia->getKey(), $pratica->pratica_stato_id);
        $storico = $pratica->praticaStatusHistory()->firstOrFail();
        $this->assertSame('agenti', $storico->source);
        $this->assertSame('Rinuncia cliente da produttore: ha cambiato idea', $storico->notes);
        $this->assertTrue($pratica->praticaStatusHistory()->daVisionare()->exists());
        $this->assertTrue(Pratica::query()->conStatiDaVisionare()->whereKey($pratica->getKey())->exists());

        Filament::setCurrentPanel('admin');
        $this->actingAs($istruttoreUser);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertNotified()
            ->assertActionVisible('presaVisione')
            ->callAction('presaVisione')
            ->assertHasNoActionErrors();

        $this->assertFalse($pratica->praticaStatusHistory()->daVisionare()->exists());
        $this->assertSame($istruttoreUser->id, (int) $pratica->praticaStatusHistory()->first()->seen_by);
    }

    public function test_a_change_made_by_the_instructor_needs_no_acknowledgement(): void
    {
        $istruttoreUser = User::factory()->create(['role' => 'super_admin']);
        $istruttore = Employee::query()->create(['name' => 'Istruttore', 'user_id' => $istruttoreUser->id]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZRIN-'.Str::upper(Str::random(4)), 'employee_id' => $istruttore->getKey(), 'upload_at' => now()]);
        $pratica->praticaStatusHistory()->create(['status_to' => 'Inserita', 'changed_at' => now(), 'source' => 'admin', 'user_id' => $istruttoreUser->id]);

        $this->assertFalse($pratica->praticaStatusHistory()->daVisionare()->exists());
    }

    public function test_the_producer_can_waive_from_the_edit_page_before_the_practice_is_taken_over(): void
    {
        Filament::setCurrentPanel('agenti');
        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = Fornitore::query()->create(['name' => 'Produttore', 'piva' => '996'.random_int(10000000, 99999999), 'user_id' => $agent->id, 'is_active' => true]);
        $cessione = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZRIN-'.Str::upper(Str::random(4)), 'fornitori_id' => $fornitore->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($fornitore, $pratica);

        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);
        $this->actingAs($agent);

        Livewire::test(AgentiEditPratica::class, ['record' => $pratica->getKey()])->callAction('rinunciaCliente', ['nota' => null]);

        $this->assertSame('RINUNCIA CLIENTE', $pratica->refresh()->praticaStato->codice);
        $this->assertSame('Rinuncia cliente da produttore', $pratica->praticaStatusHistory()->firstOrFail()->notes);
    }
}
