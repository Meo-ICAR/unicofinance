<?php

namespace Tests\Feature;

use App\Enums\SimulationContractType;
use App\Filament\Agenti\Pages\Simulazione;
use App\Models\FinancingQuote;
use App\Models\KycQuestionnaire;
use App\Models\User;
use App\Support\FinancingClientFields;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\SeedsCoreData;
use Tests\Support\FakeDocumentReader;
use Tests\TestCase;
use Unico\Core\Documents\Contracts\DocumentReader;
use Unico\Core\Documents\PracticeMailReplyHandler;
use Unico\Core\Documents\PracticeSignaturePackage;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Documents\Reminders\ReminderMail;
use Unico\Core\Models\Client;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Company;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentAnalysis;
use Unico\Core\Models\DocumentRequest;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\IncomingEmail;
use Unico\Core\Models\MailAccount;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\SignatureRequest;
use Unico\Core\Seeders\ComuniSeeder;
use Unico\Core\Seeders\PerfezionamentoDocumentiSeeder;

class SimulazioneTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCoreData;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private const XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<fmresultset xmlns="http://www.filemaker.com/xml/fmresultset" version="1.0">
  <error code="0"></error>
  <resultset count="4" fetch-size="4">
    <record><field name="Importo_erogato"><data>12345,67</data></field><field name="Errore"><data>2</data></field></record>
    <record><field name="Importo_erogato"><data>10000,50</data></field><field name="Errore"><data>2</data></field></record>
    <record><field name="Importo_erogato"><data>1</data></field><field name="Errore"><data>1</data></field></record>
    <record><field name="Importo_erogato"><data>2131328,62</data></field><field name="Errore"><data>2</data></field></record>
  </resultset>
</fmresultset>
XML;

    protected function setUp(): void
    {
        parent::setUp();
        (new ComuniSeeder)->run();
        $this->seedCoreData('address_types', 'document_types', 'tasks', 'document_requirements', 'pdf_modules', 'pdf_module_fields', 'pratica_stati', 'tipoprodotto', 'tipoprodotto_sub');

        config([
            'unico-core.signature.driver' => 'fake',
            'preventivatore.url' => 'https://preventivatore.test/offerte',
            'preventivatore.passkey' => 'secret',
            'preventivatore.provvigione' => '3',
            'preventivatore.id_listino' => '0001',
            'preventivatore.id_agente' => '0001',
        ]);

        $user = User::factory()->create([
            'email' => 'agente-'.Str::uuid().'@test.local',
        ]);
        Fornitore::query()->create([
            'name' => 'Agente Simulazione',
            'piva' => '99900000099',
            'email' => 'agente@races.test',
            'tel' => '3331112222',
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

    /**
     * Carica con l'unico campo di upload tutti i documenti richiesti dal task del prodotto: il tipo
     * si riconosce dal nome del file. Ritorna gli id dei tipi per nome.
     */
    /** @return array<string, int> */
    private function caricaTuttiIDocumenti(Testable $page): array
    {
        $files = [];
        $ids = [];

        foreach ($page->get('documentiRichiesti') as $document) {
            $ids[$document['name']] = $document['document_type_id'];
            $files[] = UploadedFile::fake()->createWithContent(Str::slug($document['name']).'.pdf', "%PDF-1.4\n".$document['name']);
        }

        $page->set('documenti.files', $files);

        return $ids;
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
            ->assertSet('esito.max', 21313.29)
            ->assertSee('Salva preventivo')
            ->call('salvaPreventivo')
            ->assertSet('preventivoNumero', 1)
            ->assertSet('fase', 'preventivo')
            ->assertSet('statoPratica', 'PREVENTIVO')
            ->assertSee('Perfeziona');

        Http::assertSent(function (Request $request): bool {
            $query = $request->data();

            return $query['passkey'] === 'secret'
                && $query['Provvigione'] === '3,00'
                && $query['ID_listino'] === '0001'
                && $query['ID_agente'] === '0001'
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

        // «Busta paga 1» e «Busta paga 2»: i file della stessa famiglia prendono i numeri liberi, uno ciascuno.
        $buste = Document::query()
            ->where('documentable_id', (string) FinancingQuote::query()->first()->pratica->getKey())
            ->whereHas('documentType', fn ($query) => $query->where('name', 'like', 'Busta paga %'))
            ->with('documentType')
            ->get();
        $this->assertEqualsCanonicalizing(['Busta paga 1', 'Busta paga 2'], $buste->pluck('documentType.name')->all());

        $page->assertSet('fase', 'cliente')
            ->assertSee('Datore di lavoro')
            ->assertSee('Quota cedibile')
            ->set('cliente.first_name', 'Maria')
            ->set('cliente.name', 'Rossi')
            ->set('cliente.tax_code', 'RSSMRA80A01H501U')
            ->set('cliente.employer_id', $employer->getKey())
            ->tap(fn ($page) => $this->compilaResidenzaKycEConsensi($page))
            ->call('salvaCliente')
            ->assertSet('fase', 'firma')
            ->assertSet('statoPratica', 'FASCICOLO COMPLETO');

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
        $this->skipWithoutPdfTemplates();
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
        $page->assertSee('Cedolino pensione 1');

        Http::assertSent(function (Request $request): bool {
            // Il preventivatore riceve i parametri in una POST (form); le altre richieste registrate non interessano.
            $query = $request->data();

            return isset($query['Tipo_contratto'])
                && $query['Tipo_contratto'] === 'Prestito'
                && $query['Tipo_rapporto'] === 'Pensionato INPS'
                && $query['Importo_richiesto'] === '15000,00'
                && $query['Durata'] === '84'
                && $query['Data_decorrenza'] === '01-13-2027'
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

        $typeId = $this->caricaTuttiIDocumenti($page)['Cedolino pensione 1'];

        $this->assertContains('Cedolino pensione 1', array_column($page->get('esitiDocumenti'), 'tipo'));
        $page->assertSet('fase', 'cliente')
            ->assertSet('cliente.first_name', 'Mario')
            ->assertSet('cliente.tax_code', 'RSSMRA80A01H501U')
            ->assertSee('Reddito netto mensile')
            ->assertDontSee('Quota cedibile')
            ->assertDontSee('Datore di lavoro')
            ->tap(fn ($page) => $this->compilaResidenzaKycEConsensi($page))
            ->call('salvaCliente')
            ->assertSet('fase', 'firma')
            ->assertSet('statoPratica', 'FASCICOLO COMPLETO');

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
        // Modulistica da firmare: stampa dei moduli e invio per la firma OTP al cliente e al collaboratore.
        $page->assertSee('Modulistica da firmare')
            ->assertSee('QAV Persona fisica')
            ->assertDontSee('Informativa Privacy Compass');

        // L'informativa Compass entra solo se la banca della pratica è Compass.
        // La banca (mandante) non è un dato di base del core: si crea qui, con l'id a cui rimanda il tipo documento «Informativa Privacy Compass».
        $compass = Cliente::query()->where('id', 50)->first() ?? (new Cliente)->forceFill(['id' => 50, 'name' => 'COMPASS BANCA S.P.A.']);
        $compass->save();
        $page->set('moduli.banca', $compass->getKey())->assertSee('Informativa Privacy Compass');
        $this->assertSame($compass->getKey(), $pratica->fresh()->clienti_id);
        $this->assertSame('COMPASS BANCA S.P.A.', $pratica->fresh()->denominazione_banca);

        $moduli = DocumentType::query()->whereIn('id', app(PracticeTaskDocuments::class)->forProductStatus('Prestito', 'FASCICOLO COMPLETO', $compass->getKey())->pluck('document_type_id'))->get();
        $this->assertEqualsCanonicalizing(['QAV Persona fisica', 'Informativa Privacy Compass', 'Races VERS. 03_2026 - Fascicolo completo retail Prestiti Personali compilabile'], $moduli->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['QAV Persona fisica', 'Races VERS. 03_2026 - Fascicolo completo retail Prestiti Personali compilabile'], DocumentType::query()->whereIn('id', app(PracticeTaskDocuments::class)->forProductStatus('Prestito', 'FASCICOLO COMPLETO', null)->pluck('document_type_id'))->pluck('name')->all());

        $package = app(PracticeSignaturePackage::class);
        $before = $package->status($pratica->fresh(), $moduli);
        $this->assertSame('pronto', $before[$moduli->firstWhere('name', 'QAV Persona fisica')->id]['state']);

        $results = $package->send($pratica->fresh(), $moduli, auth()->user());
        $byName = collect($results)->keyBy('name');
        $this->assertTrue($byName['QAV Persona fisica']['ok'], $byName['QAV Persona fisica']['message']);
        $this->assertSame('inviato per la firma con OTP, insieme agli altri moduli (un solo codice)', $byName['QAV Persona fisica']['message']);
        $this->assertFalse($byName['Informativa Privacy Compass']['ok']);
        $this->assertStringContainsString('modulo PDF non disponibile', $byName['Informativa Privacy Compass']['message']);
        $this->assertTrue($byName['Races VERS. 03_2026 - Fascicolo completo retail Prestiti Personali compilabile']['ok'], $byName['Races VERS. 03_2026 - Fascicolo completo retail Prestiti Personali compilabile']['message']);

        $after = $package->status($pratica->fresh(), $moduli);
        $this->assertSame('inviato', $after[$moduli->firstWhere('name', 'QAV Persona fisica')->id]['state']);
        $richieste = SignatureRequest::query()->whereIn('document_id', Document::query()->where('documentable_type', 'pratica')->where('documentable_id', (string) $pratica->getKey())->pluck('id'))->get();
        $this->assertGreaterThanOrEqual(2, $richieste->count());
        $this->assertCount(1, $richieste->pluck('provider_ref')->unique(), 'un solo plico presso il provider: un solo OTP');

        $this->assertNotContains('salary_quote', FinancingClientFields::forContract(SimulationContractType::Prestito));
        $this->assertNotContains('employer_id', FinancingClientFields::forContract(SimulationContractType::Prestito));
        $this->assertNotContains('employer_id', FinancingClientFields::forContract(SimulationContractType::Mutuo));
        $this->assertContains('salary_quote', FinancingClientFields::forContract(SimulationContractType::Cessione));
    }

    public function test_privacy_notice_can_be_postponed_and_the_single_upload_ticks_what_it_recognizes(): void
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
            ->call('simulate')
            ->call('salvaPreventivo')
            ->call('perfeziona')
            ->assertSet('fase', 'informativa')
            ->call('posticipaInformativa')
            ->assertSet('fase', 'contatti')
            ->set('contatti.email', 'mario.rossi@example.test')
            ->set('contatti.phone', '3492995106')
            ->call('confermaContatti')
            ->assertSet('fase', 'perfezionare');

        $privacyId = $page->get('informativaRichiesta')[0]['document_type_id'];
        $this->assertSame([], $page->get('documenti.ricevuti') ?? []);

        $page->set('documenti.files', [
            UploadedFile::fake()->createWithContent('informativa-firmata.pdf', "%PDF-1.4\ninformativa"),
            UploadedFile::fake()->createWithContent('foto-vacanza.pdf', "%PDF-1.4\naltro"),
        ])
            ->assertSet('documenti.files', [])
            ->assertSet('documenti.ricevuti', [$privacyId]);

        $esiti = collect($page->get('esitiDocumenti'))->pluck('tipo', 'file');
        $this->assertSame('Informativa privacy', $esiti['informativa-firmata.pdf']);
        $this->assertNull($esiti['foto-vacanza.pdf']);

        $pratica = FinancingQuote::query()->first()->pratica;
        $unknown = Document::query()->where('documentable_id', (string) $pratica->getKey())->where('name', 'foto-vacanza.pdf')->first();
        $this->assertNotNull($unknown);
        $this->assertNull($unknown->document_type_id);
        $this->assertSame('Informativa privacy', DocumentType::find($privacyId)->name);
    }

    public function test_missing_documents_can_be_requested_by_email_and_the_reply_attachments_are_acquired(): void
    {
        Mail::fake();
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
            ->call('simulate')
            ->call('salvaPreventivo')
            ->call('perfeziona')
            ->call('posticipaInformativa')
            ->set('contatti.email', 'maria.rossi@example.test')
            ->set('contatti.phone', '3492995106')
            ->call('confermaContatti')
            ->call('richiediAlCliente');

        $pratica = FinancingQuote::query()->first()->pratica;

        Mail::assertSent(ReminderMail::class, function ($mail) use ($pratica): bool {
            return $mail->hasTo('maria.rossi@example.test')
                && str_contains($mail->subjectLine, '['.$pratica->codice_pratica.']')
                && str_contains($mail->bodyContent, 'Informativa privacy')
                && str_contains($mail->bodyContent, 'Carta d&#039;Identità');
        });

        $requests = DocumentRequest::query()->where('requestable_id', (string) $pratica->getKey())->get();
        $this->assertGreaterThanOrEqual(3, $requests->count());
        $this->assertSame(['richiesto'], $requests->pluck('status')->unique()->all());
        $this->assertStringContainsString('maria.rossi@example.test', (string) $requests->first()->notes);

        // La risposta del cliente, con gli allegati, si riconosce dal codice pratica e si acquisisce.
        $company = Company::query()->first() ?? Company::query()->create(['name' => 'Azienda di prova']);
        $account = MailAccount::query()->create(['name' => 'Prova', 'email_address' => 'prova-'.Str::uuid().'@test.local', 'protocol' => 'imap', 'imap_host' => 'imap.test', 'imap_port' => 993, 'imap_username' => 'x', 'imap_password' => 'x', 'imap_encryption' => 'ssl', 'type' => 'sollecito', 'is_active' => true]);
        $reply = IncomingEmail::query()->create([
            'company_id' => $company->getKey(),
            'mail_account_id' => $account->getKey(),
            'message_id' => '<reply-'.Str::uuid().'@example.test>',
            'from_email' => 'Maria.Rossi@example.test',
            'subject' => 'Re: Documenti ['.$pratica->codice_pratica.']',
            'received_at' => now(),
        ]);
        $identity = UploadedFile::fake()->createWithContent('carta-identita.pdf', "%PDF-1.4\nidentita");
        $taxCode = UploadedFile::fake()->createWithContent('codice-fiscale.pdf', "%PDF-1.4\ncf");
        $reply->addMedia($identity->getRealPath())
            ->usingFileName('carta-identita.pdf')
            ->toMediaCollection(IncomingEmail::ATTACHMENTS);
        $reply->addMedia($taxCode->getRealPath())
            ->usingFileName('codice-fiscale.pdf')
            ->toMediaCollection(IncomingEmail::ATTACHMENTS);

        $results = app(PracticeMailReplyHandler::class)->process($reply->fresh());

        $this->assertCount(2, $results);
        $this->assertSame(["Carta d'Identità", 'Codice fiscale / Tessera sanitaria'], collect($results)->map(fn ($r) => $r->type?->name)->all());

        $closed = DocumentRequest::query()->where('requestable_id', (string) $pratica->getKey())->where('status', 'caricato')->count();
        $this->assertSame(2, $closed);
        $this->assertSame('documenti_pratica', $reply->fresh()->classification);

        // Un mittente sconosciuto non porta documenti nella pratica.
        $other = IncomingEmail::query()->create([
            'company_id' => $company->getKey(),
            'company_id' => $company->getKey(),
            'mail_account_id' => $account->getKey(),
            'message_id' => '<reply-'.Str::uuid().'@example.test>',
            'from_email' => 'sconosciuto@spam.test',
            'subject' => 'Re: ['.$pratica->codice_pratica.']',
            'received_at' => now(),
        ]);
        $this->assertSame([], app(PracticeMailReplyHandler::class)->process($other));
    }

    public function test_a_corporate_product_asks_the_company_data_and_the_administrator_identity_documents(): void
    {
        $page = Livewire::test(Simulazione::class)
            ->fillForm([
                'tipo_contratto' => 'leasing',
                'ragione_sociale' => 'Acme S.r.l.',
                'partita_iva' => '12345678901',
                'durata' => 60,
                'importo_richiesto' => 100000,
                'data_decorrenza' => '2027-01-13',
            ])
            ->assertFormFieldHidden('sesso')
            ->assertFormFieldHidden('data_nascita')
            ->assertFormFieldHidden('tipo_rapporto')
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSee('Acme S.r.l.')
            ->assertSet('preventivatoreAssente', true)
            ->call('salvaPreventivo')
            ->assertSet('statoPratica', 'PREVENTIVO');

        $quote = FinancingQuote::query()->first();
        $pratica = $quote->pratica;
        $this->assertSame('LEASING', $pratica->tipoprodotto->name);
        $this->assertSame('Acme S.r.l.', $pratica->cognome_cliente);
        $this->assertSame('12345678901', $pratica->codice_fiscale);
        $this->assertSame('Acme S.r.l.', $quote->company_name);

        $page->call('perfeziona')
            ->call('posticipaInformativa')
            ->set('contatti.email', 'amministrazione@acme.test')
            ->set('contatti.phone', '0612345678')
            ->call('confermaContatti')
            ->assertSet('fase', 'perfezionare');

        $names = array_column($page->get('documentiRichiesti'), 'name');
        $this->assertContains('Visura camerale', $names);
        $this->assertContains('Bilancio anno precedente depositato', $names);
        $this->assertContains('Centrale Rischi ultimi 36 mesi', $names);
        $this->assertNotContains('Contratto di finanziamento', $names);
        $this->assertNotContains('Races VERS. 06_2025 - Fascicolo completo Corporate compilabile', $names);

        $this->caricaTuttiIDocumenti($page);
        $page->assertSet('fase', 'cliente');

        // I documenti d'identità sono dell'amministratore: persona collegata alla pratica, non il richiedente.
        $administrator = PraticaClient::query()->where('pratica_id', $pratica->getKey())->first();
        $this->assertSame('amministratore', $administrator->role);
        $this->assertSame('RSSMRA80A01H501U', Client::find($administrator->client_id)->tax_code);

        $page->assertSet('cliente.name', 'Acme S.r.l.')
            ->assertSet('cliente.vat_number', '12345678901')
            ->set('cliente.residenza.street', 'Via Roma')
            ->set('cliente.residenza.numero', '10')
            ->set('cliente.residenza.zip_code', '00100')
            ->set('cliente.residenza.city', 'Roma')
            ->set('cliente.residenza.province', 'rm')
            ->set('cliente.kyc.financing_purpose', 'fabbisogno_finanziario')
            ->set('cliente.kyc.legal_nature', 'societa_capitali')
            ->set('cliente.kyc.geographic_area', 'italia')
            ->call('salvaCliente')
            ->assertSet('fase', 'firma')
            ->assertSet('statoPratica', 'FASCICOLO COMPLETO');

        $company = Client::query()->where('vat_number', '12345678901')->first();
        $this->assertNotNull($company);
        $this->assertFalse($company->is_person);
        $this->assertSame('amministrazione@acme.test', $company->email);
        $this->assertSame($administrator->client_id, $company->legal_representative_id);
        $this->assertSame('richiedente', PraticaClient::query()->where('pratica_id', $pratica->getKey())->where('client_id', $company->getKey())->value('role'));
        $this->assertSame('societa_capitali', KycQuestionnaire::query()->where('client_id', $company->getKey())->first()->legal_nature->value);
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
