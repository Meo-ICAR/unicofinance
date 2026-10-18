<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaStato;

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
        $pratica = Pratica::query()->create(['codice_pratica' => 'P-1', 'codice_pratica_banca' => 'B-100', 'cognome_cliente' => 'Rossi', 'upload_at' => now(), 'pratica_stato_id' => $vecchio->getKey()]);
        $altra = Pratica::query()->create(['codice_pratica' => 'P-2', 'codice_pratica_banca' => 'B-200', 'cognome_cliente' => 'Bianchi', 'upload_at' => now(), 'pratica_stato_id' => $vecchio->getKey()]);

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
        $pratica = Pratica::query()->create(['codice_pratica' => 'P-1', 'codice_pratica_banca' => 'B-100', 'cognome_cliente' => 'Rossi', 'upload_at' => now(), 'pratica_stato_id' => $vecchio->getKey()]);

        $csv = "Codice pratica banca;Nominativo;Tipo prodotto;Stato\nB-100;;Cessione;TEST APPROVATA\n";

        Livewire::test(ListPraticas::class)
            ->callAction('importaAggiornamenti', ['file' => UploadedFile::fake()->createWithContent('aggiornamenti.csv', $csv)])
            ->assertNotified();

        $this->assertSame($vecchio->getKey(), $pratica->fresh()->pratica_stato_id);
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
