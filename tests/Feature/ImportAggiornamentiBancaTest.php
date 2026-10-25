<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

class ImportAggiornamentiBancaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_bank_file_moves_the_practices_to_the_new_state_and_reports_the_rows_it_skips(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $vecchio = PraticaStato::query()->create(['codice' => 'TEST INVIATA', 'name' => 'TEST INVIATA']);
        $nuovo = PraticaStato::query()->create(['codice' => 'TEST APPROVATA', 'name' => 'TEST APPROVATA']);
        $banca = Cliente::query()->create(['name' => 'Banca Test']);
        $pratica = Pratica::query()->create(['codice_pratica' => 'P-1', 'codice_pratica_banca' => 'B-100', 'cognome_cliente' => 'Rossi', 'clienti_id' => $banca->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $vecchio->getKey()]);
        $altra = Pratica::query()->create(['codice_pratica' => 'P-2', 'codice_pratica_banca' => 'B-200', 'cognome_cliente' => 'Bianchi', 'clienti_id' => $banca->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $vecchio->getKey()]);

        $csv = "Codice pratica banca;Nominativo;Tipo prodotto;Stato\nB-100;Mario Rossi;Cessione;test approvata\nB-200;Giulio Verdi;Cessione;TEST APPROVATA\nB-999;Anna Neri;Cessione;TEST APPROVATA\n";
        $file = UploadedFile::fake()->createWithContent('aggiornamenti.csv', $csv);

        Livewire::test(ListPraticas::class)
            ->callAction('importaAggiornamenti', ['file' => $file])
            ->assertNotified();

        $this->assertSame($nuovo->getKey(), $pratica->fresh()->pratica_stato_id);
        $this->assertSame($vecchio->getKey(), $altra->fresh()->pratica_stato_id);
        $this->assertSame('TEST APPROVATA', $pratica->praticaStatusHistory()->latest('id')->value('status_to'));
    }

    public function test_a_row_without_the_name_does_not_change_the_practice(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $vecchio = PraticaStato::query()->create(['codice' => 'TEST INVIATA', 'name' => 'TEST INVIATA']);
        PraticaStato::query()->create(['codice' => 'TEST APPROVATA', 'name' => 'TEST APPROVATA']);
        $banca = Cliente::query()->create(['name' => 'Banca Test']);
        $pratica = Pratica::query()->create(['codice_pratica' => 'P-1', 'codice_pratica_banca' => 'B-100', 'cognome_cliente' => 'Rossi', 'clienti_id' => $banca->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $vecchio->getKey()]);

        $csv = "Codice pratica banca;Nominativo;Tipo prodotto;Stato\nB-100;;Cessione;TEST APPROVATA\n";

        Livewire::test(ListPraticas::class)
            ->callAction('importaAggiornamenti', ['file' => UploadedFile::fake()->createWithContent('aggiornamenti.csv', $csv)])
            ->assertNotified();

        $this->assertSame($vecchio->getKey(), $pratica->fresh()->pratica_stato_id);
    }

    public function test_the_practice_is_found_by_surname_in_the_bank_with_the_bank_code_saved_and_the_missing_state_added(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $cessione = TipoProdotto::query()->firstOrCreate(['tipo_prodotto' => 'Cessione'], ['name' => 'Cessione']);
        $banca = Cliente::query()->create(['name' => 'Banca Test']);
        $altraBanca = Cliente::query()->create(['name' => 'Altra Banca']);
        $inserita = PraticaStato::query()->create(['codice' => 'INSERITA', 'name' => 'INSERITA', 'tipo_finanziamento' => $cessione->name, 'ordine' => 20]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'P-1', 'cognome_cliente' => 'Rossi', 'clienti_id' => $banca->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $inserita->getKey()]);
        $dellAltraBanca = Pratica::query()->create(['codice_pratica' => 'P-2', 'cognome_cliente' => 'Verdi', 'clienti_id' => $altraBanca->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $inserita->getKey()]);
        Pratica::query()->create(['codice_pratica' => 'P-3', 'cognome_cliente' => 'Neri', 'clienti_id' => $banca->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $inserita->getKey()]);

        $csv = "Cognome cedente;Stato;Codice pratica\nRossi;in attesa di perizia;B-777\nNeri;in attesa di perizia;B-778\nVerdi;in attesa di perizia;B-888\n";

        Livewire::test(ListPraticas::class)
            ->callAction('importaAggiornamenti', ['file' => UploadedFile::fake()->createWithContent('stati.csv', $csv)])
            ->assertNotified();

        $pratica->refresh();
        $nuovo = PraticaStato::query()->where('codice', 'IN ATTESA DI PERIZIA')->where('tipo_finanziamento', $cessione->name)->firstOrFail();
        $this->assertSame('B-777', $pratica->codice_pratica_banca);
        $this->assertSame($nuovo->getKey(), $pratica->pratica_stato_id);
        $this->assertSame($inserita->getKey(), $dellAltraBanca->fresh()->pratica_stato_id);
        $this->assertNull($dellAltraBanca->fresh()->codice_pratica_banca);
        $this->assertSame(['Cognome cedente', 'Stato', 'Codice pratica'], $banca->fresh()->tracciato_import['intestazioni']);
    }

    public function test_a_stored_layout_recognizes_the_bank_on_the_next_file_without_known_practices(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $cessione = TipoProdotto::query()->firstOrCreate(['tipo_prodotto' => 'Cessione'], ['name' => 'Cessione']);
        $banca = Cliente::query()->create(['name' => 'Banca Test']);
        $stato = PraticaStato::query()->create(['codice' => 'INSERITA', 'name' => 'INSERITA', 'tipo_finanziamento' => $cessione->name, 'ordine' => 20]);
        $nuovo = PraticaStato::query()->create(['codice' => 'DELIBERATA', 'name' => 'DELIBERATA', 'tipo_finanziamento' => $cessione->name, 'ordine' => 30]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'P-1', 'cognome_cliente' => 'Rossi', 'clienti_id' => $banca->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $stato->getKey()]);
        $banca->forceFill(['tracciato_import' => ['firma' => 'cognome-cedente|stato', 'intestazioni' => ['Cognome cedente', 'Stato'], 'colonne' => ['codice' => null, 'nominativo' => 0, 'prodotto' => null, 'stato' => 1, 'banca' => null]]])->save();

        Livewire::test(ListPraticas::class)
            ->callAction('importaAggiornamenti', ['file' => UploadedFile::fake()->createWithContent('stati.csv', "Cognome cedente;Stato\nRossi;Deliberata\n")])
            ->assertNotified();

        $this->assertSame($nuovo->getKey(), $pratica->fresh()->pratica_stato_id);
    }

    public function test_a_file_of_an_unknown_bank_changes_nothing(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $stato = PraticaStato::query()->create(['codice' => 'INSERITA', 'name' => 'INSERITA', 'tipo_finanziamento' => 'Cessione', 'ordine' => 20]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'P-1', 'cognome_cliente' => 'Rossi', 'upload_at' => now(), 'pratica_stato_id' => $stato->getKey()]);

        Livewire::test(ListPraticas::class)
            ->callAction('importaAggiornamenti', ['file' => UploadedFile::fake()->createWithContent('stati.csv', "Cognome;Stato\nRossi;DELIBERATA\n")])
            ->assertNotified();

        $this->assertSame($stato->getKey(), $pratica->fresh()->pratica_stato_id);
        $this->assertSame(0, PraticaStato::query()->where('codice', 'DELIBERATA')->count());
    }

    public function test_a_surname_shared_by_two_practices_of_the_bank_needs_the_bank_code(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $cessione = TipoProdotto::query()->firstOrCreate(['tipo_prodotto' => 'Cessione'], ['name' => 'Cessione']);
        $banca = Cliente::query()->create(['name' => 'Banca Test']);
        $stato = PraticaStato::query()->create(['codice' => 'INSERITA', 'name' => 'INSERITA', 'tipo_finanziamento' => $cessione->name, 'ordine' => 20]);
        $prima = Pratica::query()->create(['codice_pratica' => 'P-1', 'cognome_cliente' => 'Rossi', 'clienti_id' => $banca->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $stato->getKey()]);
        $seconda = Pratica::query()->create(['codice_pratica' => 'P-2', 'cognome_cliente' => 'Rossi', 'clienti_id' => $banca->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'upload_at' => now(), 'pratica_stato_id' => $stato->getKey()]);

        $csv = "Cognome;Stato\nRossi;DELIBERATA\n";

        Livewire::test(ListPraticas::class)
            ->callAction('importaAggiornamenti', ['file' => UploadedFile::fake()->createWithContent('stati.csv', $csv)])
            ->assertNotified();

        $this->assertSame($stato->getKey(), $prima->fresh()->pratica_stato_id);
        $this->assertSame($stato->getKey(), $seconda->fresh()->pratica_stato_id);
        $this->assertSame(0, PraticaStato::query()->where('codice', 'DELIBERATA')->count());
    }

    public function test_the_waiting_for_the_bank_button_sets_the_filters(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $inviata = Pratica::query()->create(['codice_pratica' => 'P-A', 'cognome_cliente' => 'A', 'upload_at' => now(), 'sended_at' => '2026-10-01']);
        $nonInviata = Pratica::query()->create(['codice_pratica' => 'P-B', 'cognome_cliente' => 'B', 'upload_at' => now()]);
        $perfezionata = Pratica::query()->create(['codice_pratica' => 'P-C', 'cognome_cliente' => 'C', 'upload_at' => now(), 'sended_at' => '2026-10-01', 'erogated_at' => '2026-10-10']);
        $respinta = Pratica::query()->create(['codice_pratica' => 'P-D', 'cognome_cliente' => 'D', 'upload_at' => now(), 'sended_at' => '2026-10-01', 'rejected_at' => '2026-10-05']);

        Livewire::test(ListPraticas::class)
            ->callAction('inAttesaDellaBanca')
            ->assertCanSeeTableRecords([$inviata])
            ->assertCanNotSeeTableRecords([$nonInviata, $perfezionata, $respinta]);
    }
}
