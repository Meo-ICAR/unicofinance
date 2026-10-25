<?php

namespace Tests\Feature;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\Pratica;
use App\Models\Provvigione;
use App\Services\Importazione\MediafacileApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsCoreData;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Company;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\TipoProdotto;

class ImportMediafacileTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCoreData;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core', 'proforma'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCoreData('tipoprodotto', 'tipoprodotto_sub', 'address_types', 'document_types');

        config(['services.mediafacile.base_url' => 'https://mediafacile.test/ws', 'services.mediafacile.header_key' => 'chiave']);
    }

    /** @param  list<array<string, string>>  $righe */
    private function tsv(array $intestazioni, array $righe): string
    {
        return implode("\n", [implode("\t", $intestazioni), ...array_map(fn (array $riga): string => implode("\t", array_map(fn (string $campo): string => $riga[$campo] ?? '', $intestazioni)), $righe)]);
    }

    public function test_state_synonyms_are_mapped_to_the_canonical_state_without_creating_a_new_one(): void
    {
        TipoProdotto::query()->firstOrCreate(['name' => 'Cessione'], ['tipo_prodotto' => 'Cessione']);
        $inserita = PraticaStato::query()->create(['codice' => 'INSERITA', 'tipo_finanziamento' => 'Cessione', 'name' => 'INSERITA', 'ordine' => 20, 'colore' => 'gray']);
        $intestazioni = ['ID Pratica', 'Cognome Cliente', 'Codice Fiscale', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Data Inserimento Pratica', 'Stato Pratica', 'Pratica_terzi'];
        $riga = ['ID Pratica' => 'QT90010', 'Cognome Cliente' => 'Rossi', 'Codice Fiscale' => 'RSSMRA80A01H501U', 'Denominazione Agente' => 'AGENTE IMPORT', 'Partita IVA Agente' => '12345678903', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Data Inserimento Pratica' => '10/08/2026', 'Stato Pratica' => '  Inserito ', 'Pratica_terzi' => 'NO'];

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [$riga]))]);

        $this->artisan('pratiche:import-api', ['--start-date' => '2026-08-01', '--end-date' => '2026-08-31'])->assertSuccessful();

        $this->assertSame($inserita->getKey(), Pratica::query()->where('codice_pratica', 'QT90010')->firstOrFail()->pratica_stato_id);
        $this->assertSame(0, PraticaStato::query()->where('codice', 'INSERITO')->count());
    }

    public function test_the_practices_are_imported_with_references_to_product_state_producer_and_bank(): void
    {
        $prodotto = TipoProdotto::query()->firstOrCreate(['name' => 'Cessione'], ['tipo_prodotto' => 'Cessione']);
        $intestazioni = ['ID Pratica', 'Cognome Cliente', 'Nome Cliente', 'Codice Fiscale', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Descrizione Prodotto', 'Data Inserimento Pratica', 'Stato Pratica', 'Data_invio_istruttoria', 'Data_delibera_banca', 'Data_erogazione', 'Data Annullamento', 'Montante', 'Importo_Erogazione', 'Pratica_terzi'];
        $riga = ['ID Pratica' => 'QT90001', 'Cognome Cliente' => 'Rossi', 'Nome Cliente' => 'Mario', 'Codice Fiscale' => 'RSSMRA80A01H501U', 'Denominazione Agente' => 'AGENTE IMPORT', 'Partita IVA Agente' => '12345678903', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Descrizione Prodotto' => 'CQS', 'Data Inserimento Pratica' => '10/08/2026', 'Stato Pratica' => 'Inserita', 'Montante' => '12.000,50', 'Pratica_terzi' => 'NO'];
        $terzi = ['ID Pratica' => 'QT90002', 'Denominazione Agente' => 'AGENTE IMPORT', 'Denominazione Banca' => 'BANCA IMPORT', 'Pratica_terzi' => 'SI'];

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [$riga, $terzi]))]);

        $this->artisan('pratiche:import-api', ['--start-date' => '2026-08-01', '--end-date' => '2026-08-31'])->assertSuccessful();

        $pratica = Pratica::query()->where('codice_pratica', 'QT90001')->firstOrFail();
        $this->assertSame($prodotto->getKey(), $pratica->tipoprodotto_id);
        $this->assertSame('INSERITA', $pratica->praticaStato->codice);
        $this->assertSame('Cessione', $pratica->praticaStato->tipo_finanziamento);
        $this->assertSame('AGENTE IMPORT', Fornitore::query()->find($pratica->fornitori_id)->name);
        $this->assertSame('BANCA IMPORT', Cliente::query()->find($pratica->clienti_id)->name);
        $this->assertEquals(12000.5, $pratica->amount);
        $this->assertSame('2026-08-10', $pratica->data_inserimento_pratica->toDateString());
        $this->assertSame(1, $pratica->praticaStatusHistory()->where('source', 'mediafacile')->count());
        $this->assertSame('Mario', $pratica->nome_cliente);
        $this->assertSame('Rossi', $pratica->cognome_cliente);

        // Il richiedente diventa un cliente, riconosciuto dal codice fiscale; la società è Races Finance.
        $cliente = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->firstOrFail();
        $this->assertSame('Rossi', $cliente->name);
        $this->assertSame('Mario', $cliente->first_name);
        $this->assertTrue($cliente->is_person);
        // Anche il cliente nato dall'importazione ha residenza, domicilio e i due documenti d'identità da raccogliere.
        $this->assertSame(2, $cliente->addresses()->count());
        $this->assertSame(2, $cliente->documents()->count());
        $this->assertSame(Company::query()->where('name', 'like', 'Races Finance%')->value('id'), $pratica->company_id);
        $this->assertSame($pratica->company_id, $cliente->company_id);
        // La scheda è legata alla pratica come richiedente, anche rilanciando l'importazione.
        $this->assertSame(['richiedente'], $pratica->praticaClients()->where('client_id', $cliente->getKey())->pluck('role')->all());

        // Le pratiche di terzi si importano, segnate come tali.
        $this->assertTrue((bool) Pratica::query()->where('codice_pratica', 'QT90002')->firstOrFail()->is_notowned);
        $this->assertSame(1, Cliente::query()->where('name', 'BANCA IMPORT')->count());
    }

    public function test_the_rejection_date_is_never_in_the_future_and_is_today_when_a_rejected_practice_has_none(): void
    {
        TipoProdotto::query()->firstOrCreate(['name' => 'Cessione'], ['tipo_prodotto' => 'Cessione']);
        $intestazioni = ['ID Pratica', 'Denominazione Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Stato Pratica', 'Data Annullamento', 'Pratica_terzi'];
        $base = ['Denominazione Agente' => 'AGENTE IMPORT', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Pratica_terzi' => 'NO'];
        $righe = [
            $base + ['ID Pratica' => 'QT91001', 'Stato Pratica' => 'PRATICA RESPINTA', 'Data Annullamento' => now()->addDays(10)->format('d/m/Y')],
            $base + ['ID Pratica' => 'QT91002', 'Stato Pratica' => 'PRATICA RESPINTA', 'Data Annullamento' => ''],
            $base + ['ID Pratica' => 'QT91003', 'Stato Pratica' => 'Inserita', 'Data Annullamento' => ''],
            $base + ['ID Pratica' => 'QT91004', 'Stato Pratica' => 'DECLINATA', 'Data Annullamento' => '03/08/2026'],
        ];
        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, $righe))]);

        $this->artisan('pratiche:import-api', ['--start-date' => now()->subDays(30)->toDateString(), '--end-date' => now()->toDateString(), '--no-close' => true])->assertSuccessful();

        $rifiutata = fn (string $codice) => Pratica::query()->where('codice_pratica', $codice)->firstOrFail()->rejected_at?->toDateString();
        $this->assertSame(today()->toDateString(), $rifiutata('QT91001'), 'data futura → oggi');
        $this->assertSame(today()->toDateString(), $rifiutata('QT91002'), 'respinta senza data → oggi');
        $this->assertNull($rifiutata('QT91003'), 'pratica viva senza data resta senza');
        $this->assertSame('2026-08-03', $rifiutata('QT91004'), 'data passata invariata');
    }

    public function test_a_second_import_updates_the_practice_and_records_the_state_change(): void
    {
        TipoProdotto::query()->firstOrCreate(['name' => 'Cessione'], ['tipo_prodotto' => 'Cessione']);
        $intestazioni = ['ID Pratica', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Stato Pratica', 'Data_erogazione', 'Pratica_terzi'];
        $base = ['ID Pratica' => 'QT90003', 'Denominazione Agente' => 'AGENTE IMPORT', 'Partita IVA Agente' => '12345678903', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Pratica_terzi' => 'NO'];

        Http::fake(['mediafacile.test/*' => Http::sequence()
            ->push($this->tsv($intestazioni, [$base + ['Stato Pratica' => 'Inserita']]))
            ->push($this->tsv($intestazioni, [$base + ['Stato Pratica' => 'LIQUIDATA', 'Data_erogazione' => '05/09/2026']]))]);

        $this->artisan('pratiche:import-api')->assertSuccessful();
        $this->artisan('pratiche:import-api')->assertSuccessful();

        $pratica = Pratica::query()->where('codice_pratica', 'QT90003')->firstOrFail();
        $this->assertSame(1, Pratica::query()->where('codice_pratica', 'QT90003')->count());
        $this->assertSame('LIQUIDATA', $pratica->praticaStato->codice);
        $this->assertSame('2026-09-05', $pratica->erogated_at->toDateString());
        $this->assertSame(2, $pratica->praticaStatusHistory()->count());
        $this->assertSame('INSERITA', $pratica->praticaStatusHistory()->orderByDesc('id')->first()->status_from);
    }

    public function test_the_commissions_are_linked_to_the_practice_by_code_and_aligned(): void
    {
        $produttore = Fornitore::query()->create(['name' => 'AGENTE COMPENSI', 'piva' => '12345678903', 'is_active' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'QT90004', 'cognome_cliente' => 'Rossi', 'fornitori_id' => $produttore->getKey(), 'erogated_at' => '2026-09-05', 'upload_at' => now()]);
        $intestazioni = ['ID Compenso', 'Data Inserimento', 'Descrizione', 'Tipo', 'Importo', 'Importo Effettivo', 'Stato', 'Data Pagamento', 'N. Fattura', 'Data Fattura', 'Data Status', 'Status Compenso', 'Denominazione Riferimento', 'Entrata Uscita', 'ID Pratica', 'Agente', 'Istituto finanziario', 'Partita IVA Agente', 'Codice Fiscale Agente', 'ANNULLATA'];
        $uscita = ['ID Compenso' => 'cmp-0001', 'Data Inserimento' => '06/09/2026', 'Descrizione' => 'Provvigione', 'Tipo' => 'provvigione', 'Importo' => '150,25', 'Status Compenso' => 'Pratica perfezionata', 'Denominazione Riferimento' => 'AGENTE COMPENSI', 'Entrata Uscita' => 'Uscita', 'ID Pratica' => 'QT90004', 'Agente' => 'AGENTE COMPENSI', 'Partita IVA Agente' => 'DA-VERIFICARE'];
        $senzaPratica = ['ID Compenso' => 'cmp-0002', 'Importo' => '10', 'Entrata Uscita' => 'Uscita', 'ID Pratica' => 'QT-INESISTENTE', 'Denominazione Riferimento' => 'AGENTE COMPENSI', 'Agente' => 'AGENTE COMPENSI'];
        $stornata = ['ID Compenso' => 'cmp-0003', 'Descrizione' => 'Altro', 'Importo' => '5', 'Status Compenso' => 'Stornata', 'Entrata Uscita' => 'Uscita', 'ID Pratica' => 'QT90004', 'Denominazione Riferimento' => 'AGENTE COMPENSI', 'Agente' => 'AGENTE COMPENSI'];

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [$uscita, $senzaPratica, $stornata]))]);

        $this->artisan('provvigioni:import-api')->assertSuccessful();

        $provvigione = Provvigione::query()->findOrFail('cmp-0001');
        $this->assertSame('QT90004', $provvigione->id_pratica);
        $this->assertNull($provvigione->pratica_id, 'La pratica non esiste nel database di Proforma: nessun riferimento locale.');
        $this->assertEquals(150.25, $provvigione->importo);
        $this->assertSame('2026-09-06', $provvigione->data_inserimento_compenso->toDateString());
        $this->assertSame('Inserito', $provvigione->stato, 'Pratica perfezionata: il compenso è maturato.');
        $this->assertSame('12345678903', $provvigione->piva, 'La partita IVA non valida si sostituisce con quella del produttore.');
        $this->assertSame('2026-09-05', $provvigione->erogated_at->toDateString());
        $this->assertTrue((bool) Provvigione::query()->findOrFail('cmp-0003')->annullato);

        // Gli stati dei compensi che mancavano si aggiungono in lettura.
        $this->assertSame(3, DB::connection('proforma')->table('provvigioni_statos')->whereIn('stato', ['Inserito', 'Pagato', 'Sospeso'])->count());
        $this->assertTrue((bool) DB::connection('proforma')->table('compensos')->where('status_compenso', 'Pratica perfezionata')->value('isperfezionato'));
        $this->assertFalse((bool) DB::connection('proforma')->table('compensos')->where('status_compenso', 'Stornata')->value('isperfezionato'));
        $this->assertNull(Provvigione::query()->find('cmp-0002'));
        $this->assertSame($pratica->getKey(), $provvigione->pratica->getKey());
        $this->assertSame(2, $pratica->provvigioni()->count());
    }

    public function test_a_practice_missing_from_a_later_export_is_marked_rejected(): void
    {
        TipoProdotto::query()->firstOrCreate(['name' => 'Cessione'], ['tipo_prodotto' => 'Cessione']);
        $intestazioni = ['ID Pratica', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Stato Pratica', 'Data Inserimento Pratica', 'Pratica_terzi'];
        $riga = fn (string $codice): array => ['ID Pratica' => $codice, 'Denominazione Agente' => 'AGENTE IMPORT', 'Partita IVA Agente' => '12345678903', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Stato Pratica' => 'Inserita', 'Data Inserimento Pratica' => now()->subDays(5)->format('d/m/Y'), 'Pratica_terzi' => 'NO'];

        Http::fake(['mediafacile.test/*' => Http::sequence()
            ->push($this->tsv($intestazioni, [$riga('QT90010'), $riga('QT90011')]))
            ->push($this->tsv($intestazioni, [$riga('QT90010')]))]);

        $this->artisan('pratiche:import-api')->assertSuccessful();

        // L'importazione successiva avviene giorni dopo.
        Pratica::query()->whereIn('codice_pratica', ['QT90010', 'QT90011'])->update(['upload_at' => now()->subDay()]);

        // Una pratica creata nell'app, una già erogata e una fuori dall'intervallo non si toccano.
        $app = Pratica::query()->create(['codice_pratica' => 'SIM-9-9', 'data_inserimento_pratica' => now()->subDays(5), 'upload_at' => now()->subDay()]);
        $erogata = Pratica::query()->create(['codice_pratica' => 'QT90012', 'data_inserimento_pratica' => now()->subDays(5), 'erogated_at' => now()->subDay()->toDateString(), 'upload_at' => now()->subDay()]);
        $vecchia = Pratica::query()->create(['codice_pratica' => 'QT90013', 'data_inserimento_pratica' => now()->subDays(200), 'upload_at' => now()->subDay()]);

        $this->artisan('pratiche:import-api')->assertSuccessful();

        $this->assertNull(Pratica::query()->where('codice_pratica', 'QT90010')->firstOrFail()->rejected_at);
        $this->assertTrue(Pratica::query()->where('codice_pratica', 'QT90011')->firstOrFail()->rejected_at->isToday());
        $this->assertNull($app->fresh()->rejected_at);
        $this->assertNull($erogata->fresh()->rejected_at);
        $this->assertNull($vecchia->fresh()->rejected_at);
    }

    public function test_commissions_pointing_to_missing_practices_are_marked_cancelled_unless_already_invoiced(): void
    {
        $intestazioni = ['ID Compenso', 'Importo', 'Entrata Uscita', 'ID Pratica', 'Denominazione Riferimento', 'Agente', 'Descrizione', 'Tipo', 'Status Compenso'];
        $esistente = Pratica::query()->create(['codice_pratica' => 'QT90020', 'upload_at' => now()]);
        $nuovo = ['ID Compenso' => 'cmp-1001', 'Importo' => '10', 'Entrata Uscita' => 'Uscita', 'ID Pratica' => 'QT90020', 'Denominazione Riferimento' => 'AGENTE', 'Agente' => 'AGENTE', 'Descrizione' => 'Provvigione', 'Tipo' => 'provvigione'];
        $vuote = array_fill_keys(['Data Inserimento', 'Importo Effettivo', 'Stato', 'Data Pagamento', 'N. Fattura', 'Data Fattura', 'Data Status', 'Istituto finanziario', 'Partita IVA Agente', 'Codice Fiscale Agente', 'ANNULLATA'], '');
        $tutte = array_merge($intestazioni, array_keys($vuote));

        $crea = fn (string $id, string $codice, array $extra = []) => (new Provvigione)->forceFill([
            'id' => $id, 'id_pratica' => $codice, 'importo' => 1, 'entrata_uscita' => 'Uscita', 'denominazione_riferimento' => 'AGENTE', 'fonte' => 'mediafacile', 'annullato' => false,
        ] + $extra)->save();
        $crea('orfano-1', 'QT-NON-ESISTE');
        $crea('orfano-fatturato', 'QT-NON-ESISTE-2', ['data_fattura' => '2026-09-01']);
        $crea('buono-1', 'QT90020');

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($tutte, [$nuovo + $vuote]))]);

        $this->artisan('provvigioni:import-api')->assertSuccessful();

        $this->assertTrue((bool) Provvigione::query()->findOrFail('orfano-1')->annullato);
        $this->assertFalse((bool) Provvigione::query()->findOrFail('orfano-fatturato')->annullato);
        $this->assertFalse((bool) Provvigione::query()->findOrFail('buono-1')->annullato);
        $this->assertNotNull($esistente->getKey());
    }

    public function test_the_commission_percentages_are_calculated_on_the_product_basis_and_on_the_active_commissions(): void
    {
        $produttore = Fornitore::query()->create(['name' => 'AGENTE PERCENTUALI', 'piva' => '12345678904', 'is_active' => true]);
        $cessione = TipoProdotto::query()->where('name', 'Cessione')->firstOrFail();
        $prestito = TipoProdotto::query()->where('name', 'Prestito')->firstOrFail();
        $this->assertSame('Erogato', $cessione->tipo_provvigioni);
        $this->assertSame('Interessi', $prestito->tipo_provvigioni);
        Pratica::query()->create(['codice_pratica' => 'QT90010', 'fornitori_id' => $produttore->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'erogato' => 10000, 'upload_at' => now()]);
        Pratica::query()->create(['codice_pratica' => 'QT90012', 'fornitori_id' => $produttore->getKey(), 'tipoprodotto_id' => $cessione->getKey(), 'erogato' => 10000, 'mediation_fee_percent' => 2.5, 'upload_at' => now()]);
        Pratica::query()->create(['codice_pratica' => 'QT90011', 'fornitori_id' => $produttore->getKey(), 'tipoprodotto_id' => $prestito->getKey(), 'rata' => 200, 'nrate' => 60, 'erogato' => 10000, 'upload_at' => now()]);
        $intestazioni = ['ID Compenso', 'Data Inserimento', 'Descrizione', 'Tipo', 'Importo', 'Importo Effettivo', 'Stato', 'Data Pagamento', 'N. Fattura', 'Data Fattura', 'Data Status', 'Status Compenso', 'Denominazione Riferimento', 'Entrata Uscita', 'ID Pratica', 'Agente', 'Istituto finanziario', 'Partita IVA Agente', 'Codice Fiscale Agente', 'ANNULLATA'];
        $riga = fn (string $id, string $tipo, string $importo, string $verso, string $pratica) => ['ID Compenso' => $id, 'Tipo' => $tipo, 'Importo' => $importo, 'Entrata Uscita' => $verso, 'ID Pratica' => $pratica, 'Denominazione Riferimento' => 'AGENTE PERCENTUALI', 'Agente' => 'AGENTE PERCENTUALI', 'Partita IVA Agente' => '12345678904'];

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [
            $riga('pc-1', 'Istituto', '300', 'Entrata', 'QT90010'),
            $riga('pc-2', 'Agente', '150', 'Uscita', 'QT90010'),
            $riga('pc-3', 'Istituto', '400', 'Entrata', 'QT90011'),
            $riga('pc-4', 'Cliente', '250', 'Entrata', 'QT90012'),
            $riga('pc-5', 'Cliente', '300', 'Entrata', 'QT90010'),
        ]))]);

        $this->artisan('provvigioni:import-api')->assertSuccessful();

        $this->assertEquals(3, Provvigione::query()->findOrFail('pc-1')->percentuale, 'Cessione: 300 su 10.000 di erogato.');
        $this->assertEquals(50, Provvigione::query()->findOrFail('pc-2')->percentuale, 'Agente: 150 sui 300 delle provvigioni attive.');
        // La mediazione del compenso «Cliente» si riporta sulla pratica che non l'ha; quella già indicata (percentuale) non si tocca.
        $this->assertEquals(300, Pratica::query()->where('codice_pratica', 'QT90010')->firstOrFail()->mediation_fee_amount);
        $this->assertNull(Pratica::query()->where('codice_pratica', 'QT90012')->firstOrFail()->mediation_fee_amount);
        $this->assertEquals(2.5, Provvigione::query()->findOrFail('pc-4')->percentuale, 'Cliente: la percentuale è quella scritta sulla pratica.');
        $this->assertEquals(20, Provvigione::query()->findOrFail('pc-3')->percentuale, 'Prestito: 400 su 2.000 di interessi (200 × 60 − 10.000 erogato).');
    }

    public function test_rata_and_installments_are_read_from_the_product_description(): void
    {
        $intestazioni = ['ID Pratica', 'Cognome Cliente', 'Nome Cliente', 'Codice Fiscale', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Descrizione Prodotto', 'Data Inserimento Pratica', 'Stato Pratica', 'Data_invio_istruttoria', 'Data_delibera_banca', 'Data_erogazione', 'Data Annullamento', 'Montante', 'Importo_Erogazione', 'Pratica_terzi'];
        $base = ['Cognome Cliente' => 'Rossi', 'Nome Cliente' => 'Mario', 'Codice Fiscale' => 'RSSMRA80A01H501U', 'Denominazione Agente' => 'AGENTE IMPORT', 'Partita IVA Agente' => '12345678903', 'Denominazione Banca' => 'BANCA DEL FUCINO', 'Tipo Prodotto' => 'Cessione', 'Data Inserimento Pratica' => '10/08/2026', 'Stato Pratica' => 'Inserita', 'Pratica_terzi' => 'NO'];
        $completa = $base + ['ID Pratica' => 'QT90020', 'Descrizione Prodotto' => 'Cessione - BANCA DEL FUCINO - 270,50 x 120 (QT90020)'];
        $senzaRata = $base + ['ID Pratica' => 'QT90021', 'Descrizione Prodotto' => 'Cessione - BANCA DEL FUCINO - x (QT90021)'];

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [$completa, $senzaRata]))]);
        $this->artisan('pratiche:import-api', ['--no-close' => true])->assertSuccessful();

        $pratica = Pratica::query()->where('codice_pratica', 'QT90020')->firstOrFail();
        $this->assertEquals(270.5, $pratica->rata);
        $this->assertSame(120, $pratica->nrate);
        $this->assertEquals(32460, $pratica->erogato, 'Cessione e delega: l\'erogato è rata per durata.');
        $this->assertNull(Pratica::query()->where('codice_pratica', 'QT90021')->firstOrFail()->rata);

        // Una rata già indicata non si cancella se la denominazione non la riporta più.
        Pratica::query()->where('codice_pratica', 'QT90021')->update(['rata' => 199, 'nrate' => 60]);
        $this->artisan('pratiche:import-api', ['--no-close' => true])->assertSuccessful();
        $this->assertEquals(199, Pratica::query()->where('codice_pratica', 'QT90021')->firstOrFail()->rata);
    }

    public function test_the_agent_who_is_also_an_instructor_becomes_the_practice_instructor(): void
    {
        $istruttore = Employee::query()->create(['name' => 'Mario Bianchi', 'employee_roles' => ['dipendente', 'istruttore']]);
        $impiegato = Employee::query()->create(['name' => 'Luca Verdi', 'employee_roles' => ['dipendente']]);
        $altro = Employee::query()->create(['name' => 'Anna Neri', 'employee_roles' => ['istruttore']]);
        $intestazioni = ['ID Pratica', 'Cognome Cliente', 'Nome Cliente', 'Codice Fiscale', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Descrizione Prodotto', 'Data Inserimento Pratica', 'Stato Pratica', 'Data_invio_istruttoria', 'Data_delibera_banca', 'Data_erogazione', 'Data Annullamento', 'Montante', 'Importo_Erogazione', 'Pratica_terzi'];
        $riga = fn (string $id, string $agente) => ['ID Pratica' => $id, 'Cognome Cliente' => 'Rossi', 'Nome Cliente' => 'Mario', 'Codice Fiscale' => 'RSSMRA80A01H501U', 'Denominazione Agente' => $agente, 'Partita IVA Agente' => '---', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Data Inserimento Pratica' => '10/08/2026', 'Stato Pratica' => 'Inserita', 'Pratica_terzi' => 'NO'];

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [$riga('QT90030', 'BIANCHI MARIO'), $riga('QT90031', 'LUCA VERDI'), $riga('QT90032', 'Agente Esterno')]))]);
        $this->artisan('pratiche:import-api', ['--no-close' => true])->assertSuccessful();

        $this->assertSame($istruttore->getKey(), Pratica::query()->where('codice_pratica', 'QT90030')->firstOrFail()->employee_id, 'Il nome coincide con un istruttore, anche con le parole in ordine diverso.');
        $this->assertNull(Pratica::query()->where('codice_pratica', 'QT90031')->firstOrFail()->employee_id, 'Un dipendente che non è istruttore non conta.');
        $this->assertNull(Pratica::query()->where('codice_pratica', 'QT90032')->firstOrFail()->employee_id);

        // Un istruttore già assegnato non si cambia alla successiva importazione.
        Pratica::query()->where('codice_pratica', 'QT90030')->update(['employee_id' => $altro->getKey()]);
        $this->artisan('pratiche:import-api', ['--no-close' => true])->assertSuccessful();
        $this->assertSame($altro->getKey(), Pratica::query()->where('codice_pratica', 'QT90030')->firstOrFail()->employee_id);
        $this->assertNotNull($impiegato);
    }

    public function test_every_download_is_recorded_in_the_api_calls_without_the_key(): void
    {
        Http::fake(['mediafacile.test/*' => Http::sequence()->push($this->tsv(['ID Pratica'], [['ID Pratica' => 'QT1']]))->whenEmpty(Http::response('errore', 500))]);

        app(MediafacileApi::class)->scarica('pratiche', now()->subDay(), now());

        try {
            app(MediafacileApi::class)->scarica('compensi', now()->subDay(), now());
        } catch (\RuntimeException) {
        }

        $riuscita = ApiCall::query()->where('operation', 'pratiche')->firstOrFail();
        $fallita = ApiCall::query()->where('operation', 'compensi')->firstOrFail();

        $this->assertSame(ApiProvider::Mediafacile, $riuscita->provider);
        $this->assertSame(ApiCallStatus::Success, $riuscita->status);
        $this->assertSame('1 righe', $riuscita->summary);
        $this->assertSame(ApiCallStatus::Failed, $fallita->status);
        $this->assertSame(500, $fallita->http_status);
        $this->assertStringNotContainsString('chiave', json_encode($riuscita->request));
    }

    public function test_a_practice_in_presa_in_carico_is_not_taken_back_by_the_import_but_later_states_apply(): void
    {
        TipoProdotto::query()->firstOrCreate(['name' => 'Cessione'], ['tipo_prodotto' => 'Cessione']);
        $intestazioni = ['ID Pratica', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Stato Pratica', 'Pratica_terzi'];
        $base = ['ID Pratica' => 'QT90004', 'Denominazione Agente' => 'AGENTE IMPORT', 'Partita IVA Agente' => '12345678903', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Pratica_terzi' => 'NO'];

        Http::fake(['mediafacile.test/*' => Http::sequence()
            ->push($this->tsv($intestazioni, [$base + ['Stato Pratica' => 'Inserita']]))
            ->push($this->tsv($intestazioni, [$base + ['Stato Pratica' => 'Inserita']]))
            ->push($this->tsv($intestazioni, [$base + ['Stato Pratica' => 'CARICATA BANCA']]))]);

        $this->artisan('pratiche:import-api')->assertSuccessful();

        $pratica = Pratica::query()->where('codice_pratica', 'QT90004')->firstOrFail();
        $presa = PraticaStato::query()->firstOrCreate(['codice' => 'PRESA IN CARICO', 'tipo_finanziamento' => 'Cessione'], ['name' => 'PRESA IN CARICO', 'ordine' => 0, 'colore' => 'gray']);
        $pratica->update(['pratica_stato_id' => $presa->getKey()]);

        $this->artisan('pratiche:import-api')->assertSuccessful();
        $this->assertSame('PRESA IN CARICO', $pratica->fresh()->praticaStato->codice, 'Mediafacile non conosce la presa in carico e non riporta indietro lo stato.');

        $this->artisan('pratiche:import-api')->assertSuccessful();
        $this->assertSame('CARICATA BANCA', $pratica->fresh()->praticaStato->codice, 'Gli stati successivi si applicano.');
    }

    public function test_the_import_forces_the_instructor_for_the_listed_producers(): void
    {
        TipoProdotto::query()->firstOrCreate(['name' => 'Cessione'], ['tipo_prodotto' => 'Cessione']);
        $fummo = Employee::query()->create(['name' => 'FUMMO BRIGIDA', 'is_active' => true]);
        $altro = Employee::query()->create(['name' => 'Altro Istruttore', 'is_active' => true]);
        $intestazioni = ['ID Pratica', 'Denominazione Agente', 'Partita IVA Agente', 'Denominazione Banca', 'Tipo Prodotto', 'Stato Pratica', 'Pratica_terzi'];
        $riga = fn (string $id, string $agente) => ['ID Pratica' => $id, 'Denominazione Agente' => $agente, 'Partita IVA Agente' => '---', 'Denominazione Banca' => 'BANCA IMPORT', 'Tipo Prodotto' => 'Cessione', 'Stato Pratica' => 'Inserita', 'Pratica_terzi' => 'NO'];

        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [$riga('QT95001', 'FUMMO BRIGIDA'), $riga('QT95002', 'ALTRO AGENTE')]))]);

        $this->artisan('pratiche:import-api')->assertSuccessful();

        $this->assertSame($fummo->getKey(), Pratica::query()->where('codice_pratica', 'QT95001')->value('employee_id'));
        $this->assertNull(Pratica::query()->where('codice_pratica', 'QT95002')->value('employee_id'));

        Pratica::query()->where('codice_pratica', 'QT95001')->update(['employee_id' => $altro->getKey()]);
        Http::fake(['mediafacile.test/*' => Http::response($this->tsv($intestazioni, [$riga('QT95001', 'FUMMO BRIGIDA')]))]);
        $this->artisan('pratiche:import-api')->assertSuccessful();

        $this->assertSame($fummo->getKey(), Pratica::query()->where('codice_pratica', 'QT95001')->value('employee_id'), 'Al reimport si forza di nuovo.');
    }
}
