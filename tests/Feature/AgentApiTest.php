<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Client;
use App\Models\Pratica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\Task;
use Unico\Core\Models\TipoProdotto;

/** API in ingresso per le app che consegnano richieste di finanziamento (unicoagent, prima su unicoloan). */
class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private const PRODOTTO = 'Cessione del quinto ZZ';

    /** @var array{client: ApiClient, token: string, secret: string} */
    private array $issued;

    private DocumentType $identity;

    private DocumentType $income;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('unico-core.documents_disk'));
        Storage::fake('public');
        $prodotto = TipoProdotto::query()->create(['name' => self::PRODOTTO, 'tipo_prodotto' => self::PRODOTTO]);
        $this->issued = ApiClient::issue('unicoagent');
        Fornitore::query()->create(['name' => 'ZZ AGENTE UNO', 'piva' => '99900000001', 'is_active' => true]);

        $this->identity = DocumentType::query()->create(['name' => 'Carta identità ZZ', 'slug' => 'carta-identita-zz', 'code' => 'CI-ZZ']);
        $this->income = DocumentType::query()->create(['name' => 'Busta paga ZZ', 'slug' => 'busta-paga-zz', 'code' => 'BP-ZZ']);
        $task = Task::query()->create(['name' => 'Check list ZZ', 'taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_value' => self::PRODOTTO, 'tipoprodotto_id' => $prodotto->getKey(), 'is_active' => true]);
        DocumentRequirement::query()->create(['task_id' => $task->getKey(), 'document_type_id' => $this->identity->getKey(), 'documentable' => 'client', 'is_required' => true, 'sort_order' => 1]);
        DocumentRequirement::query()->create(['task_id' => $task->getKey(), 'document_type_id' => $this->income->getKey(), 'documentable' => 'pratica', 'is_required' => true, 'sort_order' => 2]);
    }

    /** Il database core delle prove non è vuoto: si contano solo le pratiche create da questi test. */
    private function viaApi(): Builder
    {
        return Pratica::query()->where('codice_pratica', 'like', config('agent_api.pratica_prefix').'%');
    }

    /** Documenti della richiesta (pratica e cliente) creati da questi test. */
    private function documentiDellaRichiesta(): Builder
    {
        return Document::query()->whereIn('document_type_id', DocumentType::query()->where('slug', 'like', '%-zz')->pluck('id'));
    }

    /** PDF con l'intestazione vera (i controlli automatici scartano i PDF che non cominciano con %PDF). */
    private function pdf(string $name = 'a.pdf', string $marker = 'a'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% {$marker}\n".str_repeat('x', 200));
    }

    /** @return array<string, mixed> */
    private function request(string $reference = 'FIN-2026-0001', array $override = []): array
    {
        return array_replace_recursive([
            'riferimento' => $reference,
            'prodotto' => 'quinto',
            'prodotto_etichetta' => self::PRODOTTO,
            'pratica' => ['importo_richiesto' => 20000],
            'cliente' => ['cognome' => 'Rossi', 'nome' => 'Mario', 'codice_fiscale' => 'RSSMRA80A01H501U', 'email' => 'mario@example.com', 'telefono' => '+393331234567'],
            'agente' => ['partita_iva' => '99900000001'],
        ], $override);
    }

    private int $signed = 0;

    /** Ora di partenza dei timestamp firmati: fissa per tutto il test, così due firme non coincidono mai. */
    private ?int $signedAt = null;

    /** @return array<string, string> */
    private function signature(string $method, string $path, string $contentHash, ?int $timestamp = null, ?string $secret = null): array
    {
        // Ogni richiesta ha un timestamp diverso: la stessa firma ripresentata è un replay e verrebbe rifiutata.
        $timestamp ??= ($this->signedAt ??= time()) - $this->signed++;
        $signature = hash_hmac('sha256', implode('.', [$timestamp, $method, $path, $contentHash]), $secret ?? $this->issued['secret']);

        return ['X-Timestamp' => (string) $timestamp, 'X-Signature' => $signature];
    }

    /** @param  array<string, string>  $headers */
    private function signedJson(string $method, string $path, ?array $json = null, array $headers = []): TestResponse
    {
        $body = $json === null ? '' : json_encode($json);
        $headers += ['Authorization' => 'Bearer '.$this->issued['token']] + $this->signature($method, $path, hash('sha256', $body));

        return $this->call($method, $path, [], [], [], $this->server($headers), $body);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function server(array $headers): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    private function upload(string $reference, string $type, UploadedFile $file, array $extra = []): TestResponse
    {
        $path = "/api/agente/v1/richieste/{$reference}/documenti";
        $hash = hash_file('sha256', $file->getRealPath());
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->issued['token'], 'X-Content-Sha256' => $hash] + $this->signature('POST', $path, $hash);

        return $this->post($path, ['tipo' => $type, 'file' => $file] + $extra, $headers);
    }

    public function test_without_valid_credentials_the_api_answers_401(): void
    {
        $this->getJson('/api/agente/v1/tipi-documento')->assertUnauthorized();
        $this->getJson('/api/agente/v1/tipi-documento', ['Authorization' => 'Bearer sbagliato'])->assertUnauthorized();
    }

    public function test_the_signature_is_required_correct_and_recent(): void
    {
        $path = '/api/agente/v1/tipi-documento';
        $auth = ['Authorization' => 'Bearer '.$this->issued['token']];

        $this->getJson($path, $auth)->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', $path, hash('sha256', ''), null, 'segreto-sbagliato'))->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', $path, hash('sha256', ''), time() - 3600))->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', '/api/agente/v1/altro', hash('sha256', '')))->assertUnauthorized();
        $this->signedJson('GET', $path)->assertOk();
    }

    public function test_a_client_without_prefix_is_refused(): void
    {
        $this->issued['client']->forceFill(['pratica_prefix' => null])->save();

        $this->signedJson('GET', '/api/agente/v1/tipi-documento')->assertUnauthorized();
    }

    public function test_a_disabled_client_is_refused(): void
    {
        $this->issued['client']->update(['is_active' => false]);

        $this->signedJson('GET', '/api/agente/v1/tipi-documento')->assertUnauthorized();
    }

    public function test_the_body_cannot_be_altered_after_signing(): void
    {
        $path = '/api/agente/v1/richieste';
        $headers = ['Authorization' => 'Bearer '.$this->issued['token']] + $this->signature('POST', $path, hash('sha256', json_encode($this->request())));

        $this->call('POST', $path, [], [], [], $this->server($headers), json_encode($this->request('FIN-ALTRO')))->assertUnauthorized();
        $this->assertSame(0, $this->viaApi()->count());
    }

    public function test_a_declared_content_hash_does_not_replace_the_hash_of_the_body(): void
    {
        $path = '/api/agente/v1/richieste';
        $signedHash = hash('sha256', json_encode($this->request()));
        $headers = ['Authorization' => 'Bearer '.$this->issued['token'], 'X-Content-Sha256' => $signedHash] + $this->signature('POST', $path, $signedHash);

        $this->call('POST', $path, [], [], [], $this->server($headers), json_encode($this->request('FIN-ALTRO')))->assertUnauthorized();
        $this->assertSame(0, $this->viaApi()->count());
    }

    public function test_an_inactive_agent_is_refused(): void
    {
        Fornitore::query()->where('piva', '99900000001')->update(['is_active' => false]);

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertStatus(422)->assertJsonPath('codice', 'agente_sconosciuto');
    }

    public function test_a_practice_of_another_agent_blocks_the_tax_code_even_without_a_client(): void
    {
        Pratica::query()->create(['codice_pratica' => 'ZZ-2', 'codice_fiscale' => 'RSSMRA80A01H501U', 'partita_iva_agente' => '11111111111', 'upload_at' => now()]);

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-5'))->assertStatus(409)->assertJsonPath('codice', 'cliente_di_altro_agente');
    }

    public function test_a_vat_number_creates_a_company_client(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-6', ['cliente' => ['codice_fiscale' => '12345678903', 'cognome' => 'Acme Srl']]))->assertCreated();

        $client = Client::query()->where('vat_number', '12345678903')->sole();
        $this->assertTrue((bool) $client->is_company);
        $this->assertFalse((bool) $client->is_person);
    }

    public function test_signature_check_can_be_disabled_but_the_token_stays_required(): void
    {
        config(['agent_api.require_signature' => false]);

        $this->getJson('/api/agente/v1/tipi-documento', ['Authorization' => 'Bearer '.$this->issued['token']])->assertOk();
        $this->getJson('/api/agente/v1/tipi-documento')->assertUnauthorized();
    }

    public function test_a_request_creates_client_practice_in_the_initial_state_and_requested_documents(): void
    {
        $response = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $pratica = Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0001')->sole();
        $stato = PraticaStato::query()->findOrFail($pratica->pratica_stato_id);

        $response->assertJsonPath('riferimento', 'FIN-2026-0001')
            ->assertJsonPath('codice_pratica', 'WA-FIN-2026-0001')
            ->assertJsonPath('stato_pratica', $stato->name)
            ->assertJsonPath('creata', true);

        $this->assertSame('PERFEZIONARE', $stato->codice);
        $this->assertSame(self::PRODOTTO, $pratica->tipoprodotto->name);
        $this->assertSame('99900000001', $pratica->partita_iva_agente);
        $this->assertSame(Fornitore::query()->where('piva', '99900000001')->value('id'), $pratica->fornitori_id);
        $this->assertSame('RSSMRA80A01H501U', $pratica->codice_fiscale);

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->sole();
        $this->assertSame('mario@example.com', $client->email);
        $response->assertJsonPath('cliente_id', $client->getKey());

        $tipi = collect($response->json('documenti'))->pluck('stato', 'tipo');
        $this->assertEqualsCanonicalizing(['carta-identita-zz' => 'richiesto', 'busta-paga-zz' => 'richiesto'], $tipi->all());
    }

    public function test_repeating_the_request_duplicates_nothing(): void
    {
        $first = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $documents = $this->documentiDellaRichiesta()->count();

        $second = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertOk()->assertJsonPath('creata', false);

        $this->assertSame($first->json('pratica_id'), $second->json('pratica_id'));
        $this->assertSame(1, $this->viaApi()->count());
        $this->assertSame(1, Client::query()->where('tax_code', 'RSSMRA80A01H501U')->count());
        $this->assertSame($documents, $this->documentiDellaRichiesta()->count());
    }

    /** @return array<string, mixed> */
    private function fullRequest(string $reference = 'FIN-2026-0001'): array
    {
        return $this->request($reference, [
            'risposte' => ['importo' => 'i20', 'durata' => 'm120', 'rapporto' => 'Pubblico'],
            'pratica' => ['importo_fascia' => '15-20 mila', 'durata_mesi' => 120],
            'cliente' => ['data_nascita' => '1980-01-01', 'luogo_nascita' => 'Roma', 'residenza' => ['indirizzo' => 'Via Roma 1', 'citta' => 'Roma', 'provincia' => 'rm']],
            'agente' => ['nome' => 'ZZ AGENTE UNO', 'whatsapp' => '+393330000001'],
            'consenso' => ['privacy_ricevuta_il' => '2026-10-01T10:00:00+02:00', 'privacy_verificata_il' => '2026-10-01T11:00:00+02:00', 'contatto_diretto' => true],
            'documenti' => [['codice' => 'documento_identita', 'nome' => 'Documento', 'obbligo' => 'obbligatorio', 'stato' => 'ok']],
        ]);
    }

    private function activity(string $event): Builder
    {
        $pratica = Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0001')->sole();

        return $pratica->attivitaAgente()->where('event', $event)->getQuery();
    }

    public function test_the_whole_request_reaches_client_practice_and_register(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->fullRequest())->assertCreated();

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->sole();
        $pratica = Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0001')->sole();
        $residenza = $client->addresses()->where('address_type_id', 1)->sole();

        $this->assertSame('1980-01-01', $client->birth_date->toDateString());
        $this->assertSame('Roma', $client->birth_place);
        $this->assertSame(['Via Roma 1', 'Roma', 'RM'], [$residenza->street, $residenza->city, $residenza->province]);
        $this->assertTrue($client->privacy_policy_read_at->equalTo(Carbon::parse('2026-10-01T10:00:00+02:00')));
        $this->assertTrue($client->general_consent_at->equalTo(Carbon::parse('2026-10-01T11:00:00+02:00')));
        $this->assertSame(120, (int) $pratica->nrate);

        $richiesta = $this->activity('richiesta')->sole();
        $this->assertSame('m120', $richiesta->properties['risposte']['durata']);
        $this->assertSame('+393330000001', $richiesta->properties['agente']['whatsapp']);
        $this->assertTrue($richiesta->properties['contatto_diretto']);
    }

    public function test_the_first_delivery_does_not_overwrite_the_registry(): void
    {
        Pratica::query()->create(['codice_pratica' => 'ZZ-OLD', 'codice_fiscale' => 'RSSMRA80A01H501U', 'partita_iva_agente' => '99900000001', 'upload_at' => now()]);
        $existing = Client::query()->create([
            'is_person' => true, 'is_client' => true, 'name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U',
            'birth_place' => 'Napoli', 'birth_date' => '1970-05-05', 'email' => 'vecchia@example.com', 'phone' => '+390000000',
            'privacy_policy_read_at' => '2025-01-01 09:00:00', 'general_consent_at' => '2025-01-01 10:00:00', 'privacy_consent' => true,
        ]);
        $existing->addresses()->create(['address_type_id' => 1, 'name' => 'Residenza', 'street' => 'Via Vecchia 2', 'city' => 'Napoli']);

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->fullRequest())->assertCreated();

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->sole();
        $residenza = $client->addresses()->where('address_type_id', 1)->sole();
        $this->assertSame(['Napoli', '1970-05-05', 'vecchia@example.com', '+390000000'], [$client->birth_place, $client->birth_date->toDateString(), $client->email, $client->phone]);
        $this->assertSame(['2025-01-01', '2025-01-01'], [$client->privacy_policy_read_at->toDateString(), $client->general_consent_at->toDateString()]);
        // Della residenza si completa solo la provincia, che mancava.
        $this->assertSame(['Via Vecchia 2', 'Napoli', 'RM'], [$residenza->street, $residenza->city, $residenza->province]);
    }

    public function test_the_agent_updates_data_while_the_practice_is_to_be_completed(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->signedJson('PATCH', '/api/agente/v1/richieste/FIN-2026-0001', [
            'cliente' => ['cognome' => 'Rossini', 'email' => 'nuova@example.com', 'luogo_nascita' => 'Milano'],
            'pratica' => ['durata_mesi' => 96],
        ])->assertOk()->assertJsonPath('riferimento', 'FIN-2026-0001');

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->sole();
        $pratica = Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0001')->sole();
        $this->assertSame(['Rossini', 'nuova@example.com', 'Milano'], [$client->name, $client->email, $client->birth_place]);
        $this->assertSame(['Rossini', 96], [$pratica->cognome_cliente, (int) $pratica->nrate]);
        $this->assertEqualsCanonicalizing(['name', 'email', 'birth_place', 'nrate'], $this->activity('aggiornamento')->sole()->properties['campi']);
    }

    public function test_once_in_progress_only_the_consent_can_change_and_it_can_be_revoked(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->fullRequest())->assertCreated();
        $pratica = Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0001')->sole();
        $altro = PraticaStato::query()->create(['codice' => 'ISTRUTTORIA-ZZ', 'name' => 'Istruttoria ZZ', 'tipo_finanziamento' => self::PRODOTTO, 'ordine' => 1]);
        $pratica->forceFill(['pratica_stato_id' => $altro->getKey()])->save();

        $this->signedJson('PATCH', '/api/agente/v1/richieste/FIN-2026-0001', ['cliente' => ['email' => 'tardi@example.com']])
            ->assertStatus(409)->assertJsonPath('codice', 'pratica_in_lavorazione');

        $this->signedJson('PATCH', '/api/agente/v1/richieste/FIN-2026-0001', ['consenso' => ['privacy_verificata_il' => null]])
            ->assertOk()->assertJsonPath('campi_aggiornati', ['general_consent_at']);

        $client = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->sole();
        $this->assertNull($client->general_consent_at);
        $this->assertSame('mario@example.com', $client->email);

        // Consenso attivo senza data: la revoca lo toglie comunque.
        $client->forceFill(['privacy_consent' => true])->save();
        $this->signedJson('PATCH', '/api/agente/v1/richieste/FIN-2026-0001', ['consenso' => ['privacy_verificata_il' => null]])->assertOk();
        $this->assertFalse((bool) $client->refresh()->privacy_consent);
    }

    public function test_notes_reach_the_practice_once_per_origin_id(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $nota = ['testo' => 'Il cliente preferisce essere chiamato dopo le 18.', 'autore' => 'agente', 'inviata_il' => '2026-10-18T10:00:00+02:00', 'id_origine' => 'wamid.1'];

        $first = $this->signedJson('POST', '/api/agente/v1/richieste/FIN-2026-0001/note', $nota)->assertCreated()->assertJsonPath('duplicato', false);
        $this->signedJson('POST', '/api/agente/v1/richieste/FIN-2026-0001/note', $nota)->assertOk()->assertJsonPath('duplicato', true)->assertJsonPath('id', $first->json('id'));
        $this->signedJson('POST', '/api/agente/v1/richieste/FIN-2026-0001/note', ['testo' => 'x', 'autore' => 'chiunque'])->assertStatus(422)->assertJsonValidationErrors(['autore']);
        $this->signedJson('POST', '/api/agente/v1/richieste/FIN-INESISTENTE/note', $nota)->assertNotFound();

        $saved = $this->activity('nota')->sole();
        $this->assertSame('Il cliente preferisce essere chiamato dopo le 18.', $saved->description);
        $this->assertSame('agente', $saved->properties['autore']);
    }

    public function test_an_unknown_agent_is_refused(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-2', ['agente' => ['partita_iva' => '00000000000']]))
            ->assertStatus(422)->assertJsonPath('codice', 'agente_sconosciuto');

        $this->assertSame(0, $this->viaApi()->count());
    }

    public function test_a_client_of_another_agent_is_not_taken(): void
    {
        Pratica::query()->create(['codice_pratica' => 'ZZ-1', 'codice_fiscale' => 'RSSMRA80A01H501U', 'partita_iva_agente' => '11111111111', 'upload_at' => now()]);
        Client::query()->create(['is_person' => true, 'is_client' => true, 'name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U']);

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-3'))
            ->assertStatus(409)->assertJsonPath('codice', 'cliente_di_altro_agente');
    }

    public function test_required_data_is_validated(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-4', ['cliente' => ['codice_fiscale' => '']]))->assertStatus(422)->assertJsonValidationErrors(['cliente.codice_fiscale']);
        $this->signedJson('POST', '/api/agente/v1/richieste', ['riferimento' => 'con spazi!'] + $this->request())->assertStatus(422)->assertJsonValidationErrors(['riferimento']);
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-4', ['documenti' => ['non un documento']]))->assertStatus(422)->assertJsonValidationErrors(['documenti.0']);
        $this->assertSame(0, $this->viaApi()->count());
    }

    public function test_the_request_state_can_be_read(): void
    {
        $this->signedJson('GET', '/api/agente/v1/richieste/FIN-INESISTENTE')->assertNotFound();

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->signedJson('GET', '/api/agente/v1/richieste/FIN-2026-0001')->assertOk()->assertJsonCount(2, 'documenti');
    }

    public function test_a_file_lands_on_the_requested_document_with_channel_and_reference(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $response = $this->upload('FIN-2026-0001', 'carta-identita-zz', $this->pdf('cid.pdf', 'cid'), ['id_origine' => 'att-42', 'analisi' => json_encode(['leggibile' => true])])
            ->assertCreated()->assertJsonPath('duplicato', false)->assertJsonPath('ricevuto', true);

        $document = Document::query()->findOrFail($response->json('id'));
        $this->assertSame($this->identity->getKey(), $document->document_type_id);
        $this->assertSame('whatsapp', $document->channel);
        $this->assertSame('att-42', $document->channel_ref);
        $this->assertSame(['app' => 'unicoagent', 'analisi' => ['leggibile' => true]], $document->metadata['origine']);
        $this->assertTrue($document->hasMedia(Document::COLLECTION));
        $this->assertSame(64, strlen((string) $document->file_hash));
        $this->assertNotSame(DocumentStatus::PENDING, $document->status);
        $this->assertSame(2, $this->documentiDellaRichiesta()->count());
    }

    public function test_the_same_file_twice_is_not_stored_again(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $first = $this->upload('FIN-2026-0001', 'carta-identita-zz', $this->pdf('cid.pdf', 'uguale'))->assertCreated();
        $again = $this->upload('FIN-2026-0001', 'carta-identita-zz', $this->pdf('copia.pdf', 'uguale'))->assertOk()->assertJsonPath('duplicato', true);

        $this->assertSame($first->json('id'), $again->json('id'));
        $this->assertCount(1, Document::query()->findOrFail($first->json('id'))->getMedia(Document::COLLECTION));
    }

    public function test_a_file_different_from_the_declared_hash_is_refused(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $path = '/api/agente/v1/richieste/FIN-2026-0001/documenti';
        $file = UploadedFile::fake()->createWithContent('a.pdf', '%PDF contenuto vero');
        $declared = hash('sha256', 'altro contenuto');
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->issued['token'], 'X-Content-Sha256' => $declared] + $this->signature('POST', $path, hash_file('sha256', $file->getRealPath()));

        $this->post($path, ['tipo' => 'carta-identita-zz', 'file' => $file], $headers)
            ->assertStatus(422)->assertJsonPath('codice', 'hash_non_corrispondente');

        $this->assertFalse(Document::query()->where('document_type_id', $this->identity->getKey())->first()->hasMedia(Document::COLLECTION));
    }

    public function test_the_hash_is_required_and_file_types_are_limited(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $path = '/api/agente/v1/richieste/FIN-2026-0001/documenti';

        $file = $this->pdf();
        $this->post($path, ['tipo' => 'carta-identita-zz', 'file' => $file],
            ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->issued['token']] + $this->signature('POST', $path, hash_file('sha256', $file->getRealPath())))
            ->assertStatus(422)->assertJsonPath('codice', 'hash_mancante');

        $this->upload('FIN-2026-0001', 'carta-identita-zz', UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'))->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_a_list_of_files_gets_the_validation_answer(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $path = '/api/agente/v1/richieste/FIN-2026-0001/documenti';
        $file = $this->pdf();
        $hash = hash_file('sha256', $file->getRealPath());

        $this->post($path, ['tipo' => 'carta-identita-zz', 'file' => [$file]], ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->issued['token'], 'X-Content-Sha256' => $hash] + $this->signature('POST', $path, hash('sha256', '')))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_a_file_name_with_quotes_is_downloaded_with_a_valid_header(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $id = $this->upload('FIN-2026-0001', 'carta-identita-zz', $this->pdf('carta "Mario".pdf'))->assertCreated()->json('id');

        $disposition = $this->signedJson('GET', "/api/agente/v1/richieste/FIN-2026-0001/documenti/{$id}/file")->assertOk()->headers->get('Content-Disposition');

        $this->assertStringContainsString('filename=carta-_Mario_.pdf;', $disposition);
        $this->assertStringContainsString("filename*=utf-8''carta-%22Mario%22.pdf", $disposition);
    }

    public function test_an_unknown_type_or_request_is_refused(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $file = $this->pdf();

        $this->upload('FIN-2026-0001', 'tipo-che-non-esiste', $file)->assertStatus(422)->assertJsonPath('codice', 'tipo_documento_sconosciuto');
        $this->upload('FIN-INESISTENTE', 'carta-identita-zz', $file)->assertNotFound();
    }

    public function test_agent_codes_are_translated_with_the_map(): void
    {
        config(['agent_api.document_map' => ['documento_identita' => 'carta-identita-zz']]);
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->upload('FIN-2026-0001', 'documento_identita', $this->pdf())->assertCreated()->assertJsonPath('tipo', 'carta-identita-zz');
    }

    public function test_a_document_outside_the_checklist_is_added_to_the_practice(): void
    {
        $extra = DocumentType::query()->create(['name' => 'Estratto conto ZZ', 'slug' => 'estratto-conto-zz', 'code' => 'EC-ZZ']);
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->upload('FIN-2026-0001', 'estratto-conto-zz', $this->pdf())->assertCreated();

        $this->assertSame(3, $this->documentiDellaRichiesta()->count());
        $this->assertTrue(Document::query()->where('document_type_id', $extra->getKey())->sole()->hasMedia(Document::COLLECTION));
    }

    public function test_a_rejected_document_reports_the_reason(): void
    {
        $created = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $id = collect($created->json('documenti'))->firstWhere('tipo', 'carta-identita-zz')['id'];
        Document::query()->whereKey($id)->first()->update(['status' => DocumentStatus::REJECTED->value, 'rejection_note' => 'Illeggibile']);

        $document = collect($this->signedJson('GET', '/api/agente/v1/richieste/FIN-2026-0001')->json('documenti'))->firstWhere('id', $id);

        $this->assertSame('respinto', $document['stato']);
        $this->assertSame('Illeggibile', $document['motivo_rifiuto']);
    }

    public function test_the_document_types_list_shows_the_aliases(): void
    {
        config(['agent_api.document_map' => ['documento_identita' => 'carta-identita-zz']]);

        $tipi = collect($this->signedJson('GET', '/api/agente/v1/tipi-documento')->assertOk()->json('tipi'));

        $this->assertContains('carta-identita-zz', $tipi->pluck('tipo')->all());
        $this->assertContains('busta-paga-zz', $tipi->pluck('tipo')->all());
        $this->assertSame(['documento_identita'], $tipi->firstWhere('tipo', 'carta-identita-zz')['alias']);
    }

    public function test_the_command_creates_the_credentials_once(): void
    {
        $this->artisan('agent-api:client', ['name' => 'nuova-app'])->assertSuccessful()->expectsOutputToContain('Token')->expectsOutputToContain('Segreto');
        $this->artisan('agent-api:client', ['name' => 'nuova-app'])->assertFailed();

        $old = ApiClient::query()->where('name', 'nuova-app')->value('token_hash');
        $this->artisan('agent-api:client', ['name' => 'nuova-app', '--rotate' => true])->assertSuccessful();
        $this->assertNotSame($old, ApiClient::query()->where('name', 'nuova-app')->value('token_hash'));
        $this->assertSame('NUOVAAPP-', ApiClient::query()->where('name', 'nuova-app')->value('pratica_prefix'));

        $this->artisan('agent-api:client', ['name' => 'personalizzata', '--prefisso' => 'CUSTOM-'])->assertSuccessful();
        $this->artisan('agent-api:client', ['name' => 'personalizzata', '--rotate' => true])->assertSuccessful();
        $this->assertSame('CUSTOM-', ApiClient::query()->where('name', 'personalizzata')->value('pratica_prefix'));

        $this->artisan('agent-api:client', ['name' => 'doppione', '--prefisso' => 'CUSTOM-'])->assertFailed();
        $this->artisan('agent-api:client', ['name' => 'lunga', '--prefisso' => str_repeat('A', 20).'-'])->assertFailed();
        $this->artisan('agent-api:client', ['name' => 'a capo', '--prefisso' => "WB-\n"])->assertFailed();
        $this->artisan('agent-api:client', ['name' => str_repeat('nome', 8)])->assertSuccessful();
        $this->assertSame(20, strlen((string) ApiClient::query()->where('name', str_repeat('nome', 8))->value('pratica_prefix')));

        $this->artisan('agent-api:client', ['name' => 'altra', '--prefisso' => 'WA-X-'])->assertFailed();
        $this->artisan('agent-api:client', ['name' => 'wa'])->expectsOutputToContain('riservato a unicoagent')->assertFailed();
        $this->artisan('agent-api:client', ['name' => 'altra', '--prefisso' => 'WA-'])->expectsOutputToContain('riservato a unicoagent')->assertFailed();
    }

    public function test_the_same_signed_change_is_accepted_only_once(): void
    {
        $body = json_encode($this->request());
        $headers = ['Authorization' => 'Bearer '.$this->issued['token']] + $this->signature('POST', '/api/agente/v1/richieste', hash('sha256', $body), time());

        $this->call('POST', '/api/agente/v1/richieste', [], [], [], $this->server($headers), $body)->assertCreated();
        $this->call('POST', '/api/agente/v1/richieste', [], [], [], $this->server($headers), $body)->assertUnauthorized();

        $read = ['Authorization' => 'Bearer '.$this->issued['token']] + $this->signature('GET', '/api/agente/v1/richieste/FIN-2026-0001', hash('sha256', ''), time());
        $this->call('GET', '/api/agente/v1/richieste/FIN-2026-0001', [], [], [], $this->server($read))->assertOk();
        $this->call('GET', '/api/agente/v1/richieste/FIN-2026-0001', [], [], [], $this->server($read))->assertOk();
    }

    public function test_an_app_reaches_only_the_practices_it_created(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $other = ApiClient::issue('altraapp');
        $call = function (string $method, string $path, ?array $json = null) use ($other): TestResponse {
            $body = $json === null ? '' : json_encode($json);
            $headers = ['Authorization' => 'Bearer '.$other['token']] + $this->signature($method, $path, hash('sha256', $body), null, $other['secret']);

            return $this->call($method, $path, [], [], [], $this->server($headers), $body);
        };

        $call('GET', '/api/agente/v1/richieste/FIN-2026-0001')->assertNotFound();
        $call('POST', '/api/agente/v1/richieste/FIN-2026-0001/note', ['testo' => 'x', 'autore' => 'agente'])->assertNotFound();
        $call('GET', '/api/agente/v1/richieste/FIN-2026-0001/moduli')->assertNotFound();

        $call('POST', '/api/agente/v1/richieste', $this->request('FIN-2026-0001'))->assertCreated()->assertJsonPath('codice_pratica', 'ALTRAAPP-FIN-2026-0001')->assertJsonPath('riferimento', 'FIN-2026-0001');
        $this->signedJson('GET', '/api/agente/v1/richieste/FIN-2026-0001')->assertOk()->assertJsonPath('codice_pratica', 'WA-FIN-2026-0001');
    }

    public function test_a_reference_reused_for_another_client_or_agent_is_a_conflict(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-2026-0001', ['cliente' => ['codice_fiscale' => 'BNCLGU80A01H501X']]))
            ->assertStatus(409)->assertJsonPath('codice', 'riferimento_gia_usato');
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-2026-0001', ['cliente' => ['codice_fiscale' => 'rssmra80a01h501u']]))
            ->assertOk()->assertJsonPath('creata', false);
    }

    public function test_the_secret_is_encrypted_and_the_token_is_not_stored_in_clear(): void
    {
        $row = DB::table('api_clients')->where('name', 'unicoagent')->first();

        $this->assertStringNotContainsString($this->issued['secret'], $row->secret);
        $this->assertNotSame($this->issued['token'], $row->token_hash);
        $this->assertSame(hash('sha256', $this->issued['token']), $row->token_hash);
    }
}
