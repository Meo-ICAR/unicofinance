<?php

namespace Tests\Feature;

use App\Filament\Agenti\Pages\Simulazione;
use App\Filament\Agenti\Resources\Pratiche\Pages\EditPratica;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPratiche;
use App\Filament\Agenti\Widgets\DocumentiDaSeguireAgente;
use App\Filament\Agenti\Widgets\PraticheInCorsoAgente;
use App\Filament\Agenti\Widgets\PreventiviDaPerfezionare;
use App\Models\FinancingQuote;
use App\Models\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;

class DashboardAgenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    private function agente(): Fornitore
    {
        Filament::setCurrentPanel('agenti');
        $user = User::factory()->create(['role' => 'fornitore']);
        $this->actingAs($user);

        return Fornitore::query()->create(['name' => 'Agente Test', 'piva' => '996'.random_int(10000000, 99999999), 'user_id' => $user->id, 'is_active' => true]);
    }

    private function stato(string $codice): PraticaStato
    {
        return PraticaStato::query()->firstOrCreate(['codice' => $codice, 'tipo_finanziamento' => 'Cessione'], ['name' => $codice, 'ordine' => 0, 'is_working' => true, 'colore' => 'gray']);
    }

    private function preventivo(Fornitore $fornitore, int $numero, array $pratica = [], string $status = 'salvato'): FinancingQuote
    {
        $creata = Pratica::query()->create(['codice_pratica' => 'AGD-'.$numero.'-'.uniqid(), 'cognome_cliente' => 'Cliente '.$numero, 'fornitori_id' => $fornitore->getKey(), 'pratica_stato_id' => $this->stato('PREVENTIVO')->getKey(), 'upload_at' => now()] + $pratica);

        return FinancingQuote::query()->create([
            'fornitore_id' => $fornitore->getKey(), 'number' => $numero, 'contract_type' => 'cessione', 'sex' => 'M', 'birth_date' => '1980-01-01',
            'employment_relationship' => 'privato_spa', 'hired_at' => '2010-01-01', 'starts_at' => now()->toDateString(), 'duration_months' => 120,
            'assignable_quota' => 300, 'age_at_start' => 46, 'seniority_months' => 200, 'disbursed_min' => 20000, 'disbursed_max' => 25000,
            'status' => $status, 'pratica_id' => $creata->getKey(),
        ]);
    }

    public function test_quotes_to_perfect_are_the_agents_saved_ones_and_can_be_deleted_in_bulk(): void
    {
        $agente = $this->agente();
        $altro = Fornitore::query()->create(['name' => 'Altro agente', 'piva' => '995'.random_int(10000000, 99999999), 'is_active' => true]);
        $mio = $this->preventivo($agente, 1);
        $daEliminare = $this->preventivo($agente, 2);
        $perfezionato = $this->preventivo($agente, 3, [], 'perfezionato');
        $altrui = $this->preventivo($altro, 1);
        $inCarico = $this->preventivo($agente, 4, ['employee_id' => Employee::query()->create(['name' => 'Istruttore'])->getKey()]);

        Livewire::test(PreventiviDaPerfezionare::class)
            ->assertCanSeeTableRecords([$mio, $daEliminare])
            ->assertCanNotSeeTableRecords([$perfezionato, $altrui, $inCarico])
            ->callTableBulkAction('elimina', [$daEliminare]);

        $this->assertNull(FinancingQuote::query()->find($daEliminare->getKey()));
        $this->assertNull(Pratica::query()->find($daEliminare->pratica_id));
        $this->assertNotNull(FinancingQuote::query()->find($mio->getKey()));
    }

    public function test_a_quote_can_be_suspended_resumed_and_deleted_from_the_icon_actions(): void
    {
        $agente = $this->agente();
        $attivo = $this->preventivo($agente, 1);
        $sospendere = $this->preventivo($agente, 2);
        $eliminare = $this->preventivo($agente, 3);

        $widget = fn () => Livewire::test(PreventiviDaPerfezionare::class);

        $widget()
            ->assertTableActionExists('perfeziona')
            ->callTableAction('sospendi', $sospendere);
        $this->assertSame('SOSPESA', $sospendere->pratica()->first()->praticaStato->codice, 'Sospendi porta la pratica allo stato SOSPESA.');
        $this->assertSame('salvato', $sospendere->refresh()->status);

        $widget()
            ->assertCanSeeTableRecords([$attivo])
            ->assertCanNotSeeTableRecords([$sospendere])
            ->filterTable('sospesi')
            ->assertCanSeeTableRecords([$attivo, $sospendere])
            ->assertTableActionHidden('perfeziona', $sospendere)
            ->assertTableActionVisible('riprendi', $sospendere)
            ->callTableAction('riprendi', $sospendere);
        $this->assertFalse((bool) $sospendere->pratica()->first()->praticaStato->is_suspended);
        $this->assertSame('PREVENTIVO', $sospendere->pratica()->first()->praticaStato->codice);

        $widget()->callTableAction('elimina', $eliminare);
        $this->assertNull(FinancingQuote::query()->find($eliminare->getKey()));
        $this->assertNotNull(FinancingQuote::query()->find($attivo->getKey()));
    }

    public function test_a_quote_is_resumed_in_the_simulation_ready_to_be_perfected(): void
    {
        $agente = $this->agente();
        $quote = $this->preventivo($agente, 7);

        Livewire::withQueryParams(['preventivo' => $quote->getKey()])
            ->test(Simulazione::class)
            ->assertSet('quoteId', $quote->getKey())
            ->assertSet('fase', 'preventivo')
            ->assertSet('preventivoNumero', 7);

        // Un preventivo di un altro agente non si riprende.
        $altro = Fornitore::query()->create(['name' => 'Altro agente', 'piva' => '994'.random_int(10000000, 99999999), 'is_active' => true]);
        $altrui = $this->preventivo($altro, 1);

        Livewire::withQueryParams(['preventivo' => $altrui->getKey()])
            ->test(Simulazione::class)
            ->assertSet('quoteId', null);
    }

    public function test_a_practice_taken_over_by_an_instructor_can_no_longer_be_edited_by_the_agent(): void
    {
        $agente = $this->agente();
        $libera = Pratica::query()->create(['codice_pratica' => 'AGD-L', 'cognome_cliente' => 'Libera', 'fornitori_id' => $agente->getKey(), 'upload_at' => now()]);
        $inCarico = Pratica::query()->create(['codice_pratica' => 'AGD-C', 'cognome_cliente' => 'InCarico', 'fornitori_id' => $agente->getKey(), 'employee_id' => Employee::query()->create(['name' => 'Istruttore'])->getKey(), 'upload_at' => now()]);
        $this->collegaProvvigioneProduttore($agente, $libera);
        $this->collegaProvvigioneProduttore($agente, $inCarico);

        Livewire::test(EditPratica::class, ['record' => $libera->getKey()])->assertOk();
        Livewire::test(EditPratica::class, ['record' => $inCarico->getKey()])->assertForbidden();

        Livewire::test(ListPratiche::class)
            ->assertCanSeeTableRecords([$libera, $inCarico])
            ->assertTableActionVisible('edit', $libera)
            ->assertTableActionHidden('edit', $inCarico);
    }

    public function test_the_agent_widgets_show_only_his_open_practices_and_documents_to_deliver(): void
    {
        $agente = $this->agente();
        $altro = Fornitore::query()->create(['name' => 'Altro agente', 'piva' => '993'.random_int(10000000, 99999999), 'is_active' => true]);
        $mia = Pratica::query()->create(['codice_pratica' => 'AGD-M', 'cognome_cliente' => 'Mia', 'fornitori_id' => $agente->getKey(), 'upload_at' => now()]);
        $sua = Pratica::query()->create(['codice_pratica' => 'AGD-S', 'cognome_cliente' => 'Sua', 'fornitori_id' => $altro->getKey(), 'upload_at' => now()]);
        $crea = fn (Pratica $pratica, DocumentStatus $stato): Document => Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'name' => 'Doc '.$stato->value, 'status' => $stato]);
        $mancante = $crea($mia, DocumentStatus::PENDING);
        $approvato = $crea($mia, DocumentStatus::APPROVED);
        $altrui = $crea($sua, DocumentStatus::PENDING);

        Livewire::test(PraticheInCorsoAgente::class)->assertCanSeeTableRecords([$mia])->assertCanNotSeeTableRecords([$sua]);
        Livewire::test(DocumentiDaSeguireAgente::class)->assertCanSeeTableRecords([$mancante])->assertCanNotSeeTableRecords([$approvato, $altrui]);

        $this->get('/agenti')->assertOk()->assertSeeLivewire(PreventiviDaPerfezionare::class);
    }

    public function test_no_panel_registers_the_default_account_widget(): void
    {
        foreach (['admin', 'agenti'] as $pannello) {
            $this->assertNotContains(AccountWidget::class, Filament::getPanel($pannello)->getWidgets(), $pannello);
        }
    }
}
