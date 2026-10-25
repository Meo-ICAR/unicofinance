<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\User;
use App\Support\StatiSuccessivi;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

class PraticheElencoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function stato(string $name, array $flags = []): PraticaStato
    {
        return PraticaStato::query()->firstOrCreate(['codice' => $name, 'tipo_finanziamento' => 'Cessione'], ['name' => $name, 'ordine' => PraticaStato::query()->count() + 1, 'is_working' => true, 'is_estingued' => false, 'colore' => 'gray', ...$flags]);
    }

    public function test_the_suspended_ternary_filter_defaults_to_no_and_can_show_only_suspended_practices(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $attiva = Pratica::query()->create(['codice_pratica' => 'ELE-ATTIVA', 'pratica_stato_id' => $this->stato('Inserita')->getKey(), 'upload_at' => now()]);
        $sospesa = Pratica::query()->create(['codice_pratica' => 'ELE-SOSPESA', 'pratica_stato_id' => $this->stato('SOSPESA-TEST', ['is_suspended' => true])->getKey(), 'upload_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->assertCanSeeTableRecords([$attiva])
            ->assertCanNotSeeTableRecords([$sospesa])
            ->removeTableFilter('in_lavorazione')
            ->filterTable('sospese', true)
            ->assertCanSeeTableRecords([$sospesa])
            ->assertCanNotSeeTableRecords([$attiva])
            ->removeTableFilter('sospese')
            ->assertCanSeeTableRecords([$attiva, $sospesa]);
    }

    private function statoDi(string $prodotto, string $name, int $ordine, bool $rifiuto = false): PraticaStato
    {
        return PraticaStato::query()->create(['codice' => $name, 'tipo_finanziamento' => $prodotto, 'name' => $name, 'ordine' => $ordine, 'is_rejected' => $rifiuto, 'is_working' => ! $rifiuto, 'is_estingued' => false, 'colore' => 'gray']);
    }

    public function test_the_state_choice_offers_the_following_states_and_the_rejected_ones_of_the_product(): void
    {
        $bozza = $this->statoDi('Cessione', 'BOZZA-T', 10);
        $caricata = $this->statoDi('Cessione', 'CARICATA-T', 20);
        $deliberata = $this->statoDi('Cessione', 'DELIBERATA-T', 30);
        $rinuncia = $this->statoDi('Cessione', 'RINUNCIA-T', 5, true);
        $mutuo = $this->statoDi('Mutuo', 'PERIZIA-T', 40);

        $this->assertSame([$rinuncia->getKey(), $caricata->getKey(), $deliberata->getKey()], array_keys(StatiSuccessivi::per('Cessione', $caricata->getKey())));
        $this->assertNotContains($bozza->getKey(), array_keys(StatiSuccessivi::per('Cessione', $caricata->getKey())));
        $this->assertNotContains($mutuo->getKey(), array_keys(StatiSuccessivi::per('Cessione', $caricata->getKey())));
        $this->assertContains($bozza->getKey(), array_keys(StatiSuccessivi::per('Cessione', null)));
    }

    public function test_the_bulk_state_change_skips_practices_of_another_product(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $cessione = TipoProdotto::query()->firstOrCreate(['tipo_prodotto' => 'Cessione'], ['name' => 'Cessione']);
        $mutuo = TipoProdotto::query()->firstOrCreate(['tipo_prodotto' => 'Mutuo'], ['name' => 'Mutuo']);
        $inserita = $this->statoDi($cessione->name, 'INSERITA-B', 10);
        $caricata = $this->statoDi($cessione->name, 'CARICATA-B', 20);
        $mutuoInserita = $this->statoDi($mutuo->name, 'INSERITA-BM', 10);

        $inCessione = Pratica::query()->create(['codice_pratica' => 'ELE-B1', 'pratica_stato_id' => $inserita->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now()]);
        $inMutuo = Pratica::query()->create(['codice_pratica' => 'ELE-B2', 'pratica_stato_id' => $mutuoInserita->getKey(), 'tipoprodotto_id' => $mutuo->getKey(), 'upload_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->callTableBulkAction('cambiaStatoMassivo', [$inCessione, $inMutuo], data: ['stato' => $caricata->getKey()])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame($caricata->getKey(), $inCessione->fresh()->pratica_stato_id);
        $this->assertSame($mutuoInserita->getKey(), $inMutuo->fresh()->pratica_stato_id);
    }

    public function test_the_state_filter_accepts_several_states(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $primo = Pratica::query()->create(['codice_pratica' => 'ELE-S1', 'pratica_stato_id' => $this->stato('STATO UNO')->getKey(), 'upload_at' => now()]);
        $secondo = Pratica::query()->create(['codice_pratica' => 'ELE-S2', 'pratica_stato_id' => $this->stato('STATO DUE')->getKey(), 'upload_at' => now()]);
        $terzo = Pratica::query()->create(['codice_pratica' => 'ELE-S3', 'pratica_stato_id' => $this->stato('STATO TRE')->getKey(), 'upload_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->filterTable('pratica_stato_id', [$primo->pratica_stato_id, $secondo->pratica_stato_id])
            ->assertCanSeeTableRecords([$primo, $secondo])
            ->assertCanNotSeeTableRecords([$terzo]);
    }

    public function test_the_states_are_always_stored_in_uppercase(): void
    {
        $stato = PraticaStato::query()->create(['codice' => 'Caricata Banca', 'tipo_finanziamento' => 'Test', 'name' => 'Caricata Banca', 'ordine' => 0, 'colore' => 'gray']);

        $this->assertSame('CARICATA BANCA', $stato->fresh()->name);
        $this->assertSame('CARICATA BANCA', $stato->fresh()->codice);
    }

    public function test_only_practices_in_progress_are_shown_by_default(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $inLavorazione = Pratica::query()->create(['codice_pratica' => 'ELE-LAV', 'pratica_stato_id' => $this->stato('Inserita')->getKey(), 'upload_at' => now()]);
        $senzaStato = Pratica::query()->create(['codice_pratica' => 'ELE-SENZA', 'upload_at' => now()]);
        $conclusa = Pratica::query()->create(['codice_pratica' => 'ELE-CONCLUSA', 'pratica_stato_id' => $this->stato('CONCLUSA-TEST', ['is_working' => false])->getKey(), 'upload_at' => now()]);
        $sospesa = Pratica::query()->create(['codice_pratica' => 'ELE-SOSP', 'pratica_stato_id' => $this->stato('SOSPESA-LAV', ['is_suspended' => true])->getKey(), 'upload_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->assertCanSeeTableRecords([$inLavorazione, $senzaStato])
            ->assertCanNotSeeTableRecords([$conclusa, $sospesa])
            ->removeTableFilter('sospese')
            ->assertCanNotSeeTableRecords([$conclusa, $sospesa])
            ->removeTableFilter('in_lavorazione')
            ->assertCanSeeTableRecords([$inLavorazione, $senzaStato, $conclusa, $sospesa]);
    }

    public function test_the_state_can_be_changed_from_the_table_with_history_and_dates(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $da = $this->stato('Inserita');
        $approvata = $this->stato('APPROVATA-TEST', ['is_approved' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ELE-CAMBIO', 'pratica_stato_id' => $da->getKey(), 'upload_at' => now()]);

        Livewire::test(ListPraticas::class)
            ->callTableAction('cambiaStato', $pratica, data: ['stato' => $approvata->getKey(), 'motivazione' => 'Aggiornato dal portale della banca'])
            ->assertHasNoTableActionErrors();

        $pratica->refresh();
        $this->assertSame($approvata->getKey(), $pratica->pratica_stato_id);
        $this->assertTrue($pratica->approved_at->isToday());
        $history = $pratica->praticaStatusHistory()->firstOrFail();
        $this->assertSame('Aggiornato dal portale della banca', $history->notes);
        $this->assertSame('APPROVATA-TEST', $history->status_to);
    }

    public function test_the_bank_practice_code_is_saved_and_searchable(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ELE-BANCA', 'upload_at' => now()]);
        $altra = Pratica::query()->create(['codice_pratica' => 'ELE-ALTRA', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['codice_pratica_banca' => 'BNK-998877'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(ListPraticas::class)
            ->searchTable('BNK-998877')
            ->assertCanSeeTableRecords([$pratica])
            ->assertCanNotSeeTableRecords([$altra]);
    }

    public function test_the_lead_campaign_is_optional_and_saved_with_the_practice(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ELE-CAMP', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['campagna_lead' => 'Facebook ottobre'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Facebook ottobre', $pratica->fresh()->campagna_lead);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['campagna_lead' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($pratica->fresh()->campagna_lead);
    }

    public function test_the_progress_section_starts_with_the_insertion_date_which_is_not_edited_there(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $pratica = Pratica::query()->create(['codice_pratica' => 'ELE-AVAN', 'data_inserimento_pratica' => '2026-03-04', 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormFieldExists('data_inserimento_pratica')
            ->assertFormSet(['data_inserimento_pratica' => '2026-03-04'])
            ->fillForm(['cognome_cliente' => 'Rossi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-03-04', $pratica->fresh()->data_inserimento_pratica->toDateString());
    }
}
