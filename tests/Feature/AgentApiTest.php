<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Client;
use App\Models\Pratica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    /** @return array<string, string> */
    private function signature(string $method, string $path, string $contentHash, ?int $timestamp = null, ?string $secret = null): array
    {
        $timestamp ??= time();
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
    }

    public function test_the_secret_is_encrypted_and_the_token_is_not_stored_in_clear(): void
    {
        $row = DB::table('api_clients')->where('name', 'unicoagent')->first();

        $this->assertStringNotContainsString($this->issued['secret'], $row->secret);
        $this->assertNotSame($this->issued['token'], $row->token_hash);
        $this->assertSame(hash('sha256', $this->issued['token']), $row->token_hash);
    }
}
