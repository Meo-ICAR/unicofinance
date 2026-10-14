<?php

namespace Tests\Feature;

use App\Enums\SimulationContractType;
use App\Filament\Agenti\Pages\Simulazione;
use App\Models\FinancingQuote;
use App\Models\KycQuestionnaire;
use App\Models\User;
use App\Support\FinancingClientFields;
use Database\Seeders\PerfezionamentoDocumentiSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\FakeDocumentReader;
use Tests\TestCase;
use Unico\Core\Documents\Contracts\DocumentReader;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentAnalysis;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaClient;

class SimulazioneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private const XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Offerte>
  <Offerta><Importo_erogato>12.345,67</Importo_erogato><Errore>2</Errore></Offerta>
  <Offerta><Importo_erogato>10000.50</Importo_erogato><Errore>2</Errore></Offerta>
  <Offerta><Importo_erogato>1</Importo_erogato><Errore>1</Errore></Offerta>
</Offerte>
XML;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'preventivatore.url' => 'https://preventivatore.test/offerte',
            'preventivatore.passkey' => 'secret',
        ]);

        $user = User::factory()->create([
            'email' => 'agente-'.Str::uuid().'@test.local',
        ]);
        Fornitore::query()->create([
            'name' => 'Agente Simulazione',
            'piva' => '99900000099',
            'user_id' => $user->id,
        ]);

        $this->seed(PerfezionamentoDocumentiSeeder::class);
        $this->reader = new FakeDocumentReader;
        $this->app->instance(DocumentReader::class, $this->reader);

        Filament::setCurrentPanel('agenti');
        $this->actingAs($user);
    }

    private FakeDocumentReader $reader;

    /** Informativa firmata, contatti e caricamento di tutti i documenti richiesti dal task del prodotto. */
    private function perfezionaFinoAiDocumenti(Testable $page, string $email, string $phone): Testable
    {
        $page->call('perfeziona')->assertSet('fase', 'informativa');
        $privacyId = $page->get('informativaRichiesta')[0]['document_type_id'];

        $page->set('informativaAllegati.'.$privacyId, [UploadedFile::fake()->createWithContent('informativa-firmata.pdf', "%PDF-1.4\ninformativa privacy firmata")])
            ->call('caricaInformativa')
            ->assertSet('fase', 'contatti')
            ->assertSet('statoPratica', 'PREVENTIVO')
            ->assertSet('avvisiInformativa', [])
            ->call('confermaContatti')
            ->assertHasErrors(['contatti.email' => 'required', 'contatti.phone' => 'required'])
            ->set('contatti.email', $email)
            ->set('contatti.phone', $phone)
            ->call('confermaContatti')
            ->assertHasNoErrors()
            ->assertSet('statoPratica', 'PERFEZIONARE');

        return $page;
    }

    /**
     * Residenza, KYC e consensi: dati del cliente che servono a stampare i moduli.
     */
    private function compilaResidenzaKycEConsensi(Testable $page): Testable
    {
        return $page
            ->set('cliente.residenza.street', 'Via Verdi')
            ->set('cliente.residenza.numero', '7')
            ->set('cliente.residenza.zip_code', '00100')
            ->set('cliente.residenza.city', 'Roma')
            ->set('cliente.residenza.province', 'rm')
            ->set('cliente.kyc.pep_status', 'nessuna')
            ->set('cliente.kyc.financing_purpose', 'personale_familiare')
            ->set('cliente.kyc.economic_activity', 'dipendente')
            ->set('cliente.kyc.activity_sector', 'commercio_servizi')
            ->set('cliente.kyc.activity_location', 'regione_residenza')
            ->set('cliente.kyc.financing_nature', 'cessione_quinto')
            ->set('cliente.kyc.income_band', 'fino_100k')
            ->set('cliente.kyc.wealth_band', 'fino_500k')
            ->set('cliente.consensi.marketing', true)
            ->set('cliente.consensi.third_parties', true);
    }

    private function caricaTuttiIDocumenti(Testable $page): int
    {
        $contractId = 0;

        foreach ($page->get('documentiRichiesti') as $document) {
            $id = $document['document_type_id'];

            if ($document['name'] === 'Contratto di finanziamento') {
                $contractId = $id;
                DocumentType::query()->whereKey($id)->update(['regex' => 'contratto']);
            }

            $name = $document['name'] === 'Contratto di finanziamento' ? 'contratto.pdf' : 'documento-'.$id.'.pdf';
            $page->set('allegati.'.$id, [UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".$document['name'])]);
        }

        $page->call('caricaDocumenti');

        return $contractId;
    }

    public function test_agent_opens_the_simulation_and_saves_a_cession_quote(): void
    {
        Http::fake(['preventivatore.test/*' => Http::response(self::XML)]);

        $pratiche = Pratica::query()->count();

        $page = Livewire::test(Simulazione::class)
            ->fillForm([
                'tipo_contratto' => 'cessione',
                'sesso' => 'F',
                'data_nascita' => '1980-02-20',
                'tipo_rapporto' => 'statale',
                'data_assunzione' => '2015-06-01',
                'durata' => 120,
                'quota_cedibile' => 280,
                'data_decorrenza' => '2027-01-13',
            ])
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('esito.min', 10000.5)
            ->assertSet('esito.max', 12345.67)
            ->assertSee('Salva preventivo')
            ->call('salvaPreventivo')
            ->assertSet('preventivoNumero', 1)
            ->assertSet('fase', 'preventivo')
            ->assertSet('statoPratica', 'PREVENTIVO')
            ->assertSee('Perfeziona');

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['Passkey'] === 'secret'
                && $query['Tipo_contratto'] === 'Cessione'
                && $query['Tipo_rapporto'] === 'Statale'
                && $query['Importo_rata'] === '280,00'
                && $query['Data_decorrenza'] === '01-13-2027'
                && $query['Durata'] === '120'
                && $query['Sesso'] === 'F';
        });

        $quote = FinancingQuote::query()->first();
        $this->assertNotNull($quote);
        $this->assertSame('salvato', $quote->status);
        $this->assertSame($pratiche + 1, Pratica::query()->count());
        $this->assertSame('PREVENTIVO', $quote->pratica->praticaStato->codice);
        $this->assertSame('Cessione', $quote->pratica->praticaStato->tipo_finanziamento);

        $employer = Client::query()->create([
            'name' => 'Ministero Test',
            'is_person' => false,
            'company_id' => $quote->pratica->company_id,
        ]);

        $this->perfezionaFinoAiDocumenti($page, 'maria.rossi@example.test', '338 8449842');
        $this->caricaTuttiIDocumenti($page);

        $page->assertSet('fase', 'cliente')
            ->assertSee('Datore di lavoro')
            ->assertSee('Quota cedibile')
            ->set('cliente.first_name', 'Maria')
            ->set('cliente.name', 'Rossi')
            ->set('cliente.tax_code', 'RSSMRA80A01H501U')
            ->set('cliente.employer_id', $employer->getKey())
            ->tap(fn ($page) => $this->compilaResidenzaKycEConsensi($page))
            ->call('salvaCliente')
            ->assertSet('fase', 'cliente_salvato');

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->first();
        $this->assertNotNull($client);
        $this->assertSame('1980-01-01', $client->birth_date->toDateString());
        $this->assertSame('Roma (RM)', $client->birth_place);
        $this->assertSame('M', $client->sex);
        $this->assertSame($employer->getKey(), $client->employer_id);
        $this->assertContains('employer_id', FinancingClientFields::forContract(SimulationContractType::Cessione));
        $this->assertContains('employer_id', FinancingClientFields::forContract(SimulationContractType::Delega));
    }

    public function test_finalizing_a_personal_loan_creates_a_perfected_practice(): void
    {
        Http::fake(['preventivatore.test/*' => Http::response(self::XML)]);

        $page = Livewire::test(Simulazione::class)
            ->fillForm([
                'tipo_contratto' => 'prestito',
                'sesso' => 'M',
                'data_nascita' => '1955-03-01',
                'tipo_rapporto' => 'pensionato_inps',
                'durata' => 84,
                'importo_richiesto' => 15000,
                'data_decorrenza' => '2027-01-13',
            ])
            ->assertFormFieldHidden('data_assunzione')
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->call('salvaPreventivo')
            ->assertSet('statoPratica', 'PREVENTIVO');

        $this->perfezionaFinoAiDocumenti($page, 'mario.rossi@example.test', '3492995106');
        $page->assertSee('Contratto di finanziamento');

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['Tipo_contratto'] === 'Prestito'
                && $query['Tipo_rapporto'] === 'Pensionato INPS'
                && $query['Importo_richiesto'] === '15000,00'
                && $query['Durata'] === '84'
                && ! array_key_exists('Data_assunzione', $query)
                && ! array_key_exists('Importo_rata', $query);
        });

        $quote = FinancingQuote::query()->first();
        $this->assertSame('perfezionato', $quote->status);

        $pratica = $quote->pratica;
        $this->assertNotNull($pratica);
        $this->assertSame('PERFEZIONARE', $pratica->praticaStato->codice);
        $this->assertSame('Prestito', $pratica->tipoprodotto->name);
        $this->assertSame('15000.00', $pratica->amount);
        $this->assertStringStartsWith('SIM-', $pratica->codice_pratica);

        $typeId = $this->caricaTuttiIDocumenti($page);

        $this->assertContains('Contratto di finanziamento', array_column($page->get('esitiDocumenti'), 'tipo'));
        $page->assertSet('fase', 'cliente')
            ->assertSet('cliente.first_name', 'Mario')
            ->assertSet('cliente.tax_code', 'RSSMRA80A01H501U')
            ->assertSee('Reddito netto mensile')
            ->assertDontSee('Quota cedibile')
            ->assertDontSee('Datore di lavoro')
            ->tap(fn ($page) => $this->compilaResidenzaKycEConsensi($page))
            ->call('salvaCliente')
            ->assertSet('fase', 'cliente_salvato');

        $document = Document::query()
            ->where('documentable_id', (string) $pratica->getKey())
            ->where('document_type_id', $typeId)
            ->first();
        $this->assertNotNull($document);
        $this->assertSame($typeId, (int) $document->document_type_id);
        $this->assertSame('pratica', $document->documentable_type);
        $this->assertCount(1, $document->getMedia(Document::COLLECTION));
        $this->assertTrue(
            DocumentAnalysis::query()
                ->where('document_id', $document->getKey())
                ->where('kind', 'classification')
                ->where('operator_label', 'regex')
                ->exists()
        );

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->first();
        $this->assertNotNull($client);
        $this->assertSame('mario.rossi@example.test', $client->email);
        $this->assertSame('3492995106', $client->phone);
        $this->assertSame('Mario', $client->first_name);

        $address = $client->addresses()->first();
        $this->assertSame('Roma', $address->city);
        $this->assertSame('RM', $address->province);
        $this->assertSame(1, (int) $address->address_type_id);
        $this->assertNotNull($client->consent_marketing_at);
        $this->assertNotNull($client->consent_third_parties_at);
        $this->assertNull($client->consent_group_at);
        $kyc = KycQuestionnaire::query()->where('client_id', $client->getKey())->first();
        $this->assertSame('nessuna', $kyc->pep_status->value);
        $this->assertSame('complete', $kyc->status->value);
        $this->assertSame('1980-01-01', $client->birth_date->toDateString());
        $this->assertSame('Roma (RM)', $client->birth_place);
        $this->assertSame('Rossi', $pratica->fresh()->cognome_cliente);
        $this->assertTrue(PraticaClient::query()->where('pratica_id', $pratica->getKey())->where('client_id', $client->getKey())->exists());
        $this->assertNotContains('salary_quote', FinancingClientFields::forContract(SimulationContractType::Prestito));
        $this->assertNotContains('employer_id', FinancingClientFields::forContract(SimulationContractType::Prestito));
        $this->assertNotContains('employer_id', FinancingClientFields::forContract(SimulationContractType::Mutuo));
        $this->assertContains('salary_quote', FinancingClientFields::forContract(SimulationContractType::Cessione));
    }

    public function test_simulation_is_saved_when_the_quotation_url_is_missing(): void
    {
        config([
            'preventivatore.url' => null,
            'preventivatore.passkey' => null,
        ]);
        Http::fake();

        Livewire::test(Simulazione::class)
            ->fillForm([
                'tipo_contratto' => 'cessione',
                'sesso' => 'F',
                'data_nascita' => '1980-02-20',
                'tipo_rapporto' => 'statale',
                'data_assunzione' => '2015-06-01',
                'durata' => 120,
                'quota_cedibile' => 280,
                'data_decorrenza' => '2027-01-13',
            ])
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('esito', null)
            ->assertSet('preventivatoreAssente', true)
            ->assertSee('Salva preventivo')
            ->call('salvaPreventivo')
            ->assertSet('statoPratica', 'PREVENTIVO')
            ->assertSet('fase', 'preventivo');

        Http::assertNothingSent();

        $quote = FinancingQuote::query()->first();
        $this->assertNotNull($quote);
        $this->assertNull($quote->disbursed_min);
        $this->assertNull($quote->disbursed_max);
        $this->assertSame('salvato', $quote->status);
        $this->assertSame('PREVENTIVO', $quote->pratica->praticaStato->codice);
        $this->assertNull($quote->pratica->erogato);
    }
}
