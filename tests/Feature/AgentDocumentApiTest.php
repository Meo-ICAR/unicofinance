<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PdfModule;
use Unico\Core\Models\SignatureRequest;
use Unico\Core\Models\TipoProdotto;
use Unico\Core\Pdf\PdfFormFiller;
use Unico\Core\Pdf\ResolvedModuleData;
use Unico\Core\Signature\SignatureProviderManager;
use Unico\Core\Signature\SignatureRequestService;

/** Funzioni documentali per unicoagent (prima su unicoloan): moduli, template, modulo compilato, file e firma OTP. */
class AgentDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @var array{client: ApiClient, token: string, secret: string} */
    private array $issued;

    private PdfModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('unico-core.documents_disk'));
        Storage::fake(config('unico-core.pdf.modules_disk', 'public'));
        Storage::fake(config('media-library.disk_name'));
        config(['unico-core.signature.driver' => 'fake']);

        // Senza pdftk nelle prove: il modulo «compilato» è il modello stesso e l'appiattimento per la firma lo lascia com'è.
        $this->app->instance(PdfFormFiller::class, new class extends PdfFormFiller
        {
            public function fill(PdfModule $module, ResolvedModuleData $data, bool $flatten = false): string
            {
                return (string) Storage::disk(config('unico-core.pdf.modules_disk', 'public'))->get($module->file_path);
            }

            public function flattenContent(string $content): string
            {
                return $content;
            }
        });

        TipoProdotto::query()->firstOrCreate(['name' => 'Altro'], ['tipo_prodotto' => 'Altro']);
        $this->issued = ApiClient::issue('unicoagent');
        Fornitore::query()->create(['name' => 'ZZ AGENTE UNO', 'piva' => '99900000001', 'is_active' => true]);

        $type = DocumentType::query()->create(['name' => 'Modulo QAV ZZ', 'slug' => 'qav-zz', 'code' => 'QAV-ZZ', 'is_signed' => true]);
        $this->module = PdfModule::query()->create([
            'name' => 'QAV ZZ', 'file_path' => 'module/qav-zz.pdf', 'document_type_id' => $type->id, 'version' => '1', 'is_active' => true,
            'signature_slots' => [
                ['slot' => 'cliente', 'role' => 'client', 'page' => 1, 'x' => 50.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
                ['slot' => 'collaboratore', 'role' => 'collaborator', 'page' => 1, 'x' => 300.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
            ],
        ]);
        Storage::disk(config('unico-core.pdf.modules_disk', 'public'))->put('module/qav-zz.pdf', file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf')));
    }

    /** @return array<string, mixed> */
    private function request(string $reference = 'DOC-0001'): array
    {
        return [
            'riferimento' => $reference, 'prodotto' => 'quinto', 'pratica' => ['importo_richiesto' => 20000],
            'cliente' => ['cognome' => 'Rossi', 'nome' => 'Mario', 'codice_fiscale' => 'RSSMRA80A01H501U', 'email' => 'mario@example.com', 'telefono' => '+393331234567'],
            'agente' => ['partita_iva' => '99900000001'],
        ];
    }

    /** @return array<string, string> */
    private function signature(string $method, string $path, string $contentHash): array
    {
        $timestamp = time();

        return ['X-Timestamp' => (string) $timestamp, 'X-Signature' => hash_hmac('sha256', implode('.', [$timestamp, $method, $path, $contentHash]), $this->issued['secret'])];
    }

    private function signedJson(string $method, string $path, ?array $json = null): TestResponse
    {
        $body = $json === null ? '' : json_encode($json);
        $headers = ['Authorization' => 'Bearer '.$this->issued['token']] + $this->signature($method, $path, hash('sha256', $body));
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $path, [], [], [], $server, $body);
    }

    private function base(string $reference = 'DOC-0001'): string
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request($reference))->assertSuccessful();

        return "/api/agente/v1/richieste/{$reference}";
    }

    /** @return array<string, array<string, string>> */
    private function contacts(): array
    {
        return ['firmatari' => ['cliente' => ['telefono' => '+393331234567'], 'collaboratore' => ['telefono' => '+393339876543', 'email' => 'agente@example.com']]];
    }

    public function test_it_lists_the_relevant_modules_with_their_signature_boxes(): void
    {
        $base = $this->base();

        $modulo = collect($this->signedJson('GET', "{$base}/moduli")->assertOk()->json('moduli'))->firstWhere('id', $this->module->id);

        $this->assertSame('qav-zz', $modulo['tipo_documento']);
        $this->assertSame(['cliente', 'collaboratore'], $modulo['firma']);
        $this->assertIsArray($modulo['dati_mancanti']);
    }

    public function test_a_module_for_another_product_is_neither_listed_nor_downloadable(): void
    {
        $base = $this->base();
        $this->module->update(['tipi_prodotto' => ['Mutuo inesistente']]);

        $this->assertNotContains($this->module->id, collect($this->signedJson('GET', "{$base}/moduli")->assertOk()->json('moduli'))->pluck('id'));
        $this->signedJson('GET', "{$base}/moduli/{$this->module->id}/template")->assertNotFound()->assertJsonPath('codice', 'modulo_non_trovato');
        $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->assertNotFound();
    }

    public function test_it_downloads_the_empty_template_with_its_hash(): void
    {
        $base = $this->base();

        $response = $this->signedJson('GET', "{$base}/moduli/{$this->module->id}/template")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(hash('sha256', $response->getContent()), $response->headers->get('X-Content-Sha256'));
    }

    public function test_a_template_missing_from_storage_answers_404(): void
    {
        $base = $this->base();
        Storage::disk(config('unico-core.pdf.modules_disk', 'public'))->delete('module/qav-zz.pdf');

        $this->signedJson('GET', "{$base}/moduli/{$this->module->id}/template")->assertNotFound()->assertJsonPath('codice', 'template_non_disponibile');
    }

    public function test_it_fills_the_module_and_makes_it_downloadable(): void
    {
        $base = $this->base();

        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->assertCreated()->json();
        $file = $this->signedJson('GET', "{$base}/documenti/{$document['id']}/file")->assertOk();

        $this->assertTrue($document['ricevuto']);
        $this->assertStringStartsWith('%PDF', $file->getContent());
    }

    public function test_a_document_of_another_request_is_not_reachable(): void
    {
        $base = $this->base('DOC-0001');
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();
        $other = $this->base('DOC-0002');

        $this->signedJson('GET', "{$other}/documenti/{$document['id']}/file")->assertNotFound()->assertJsonPath('codice', 'documento_non_trovato');
    }

    public function test_unknown_request_and_wrong_credentials(): void
    {
        $this->signedJson('GET', '/api/agente/v1/richieste/NON-ESISTE/moduli')->assertNotFound();
        $this->getJson('/api/agente/v1/richieste/DOC-0001/moduli')->assertUnauthorized();
    }

    public function test_it_requests_the_otp_signature_and_reads_its_state(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $created = $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", $this->contacts())->assertCreated();

        $created->assertJsonPath('stato', 'sent')->assertJsonPath('firmata', false)->assertJsonCount(2, 'firmatari');
        $this->signedJson('GET', "{$base}/documenti/{$document['id']}/firma")->assertOk()->assertJsonPath('id', $created->json('id'));
    }

    public function test_a_second_signature_while_the_first_is_open_is_refused(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", $this->contacts())->assertCreated();
        $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", $this->contacts())->assertStatus(422)->assertJsonPath('codice', 'firma_non_richiesta');
    }

    public function test_without_a_signature_request_the_state_answers_404(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $this->signedJson('GET', "{$base}/documenti/{$document['id']}/firma")->assertNotFound()->assertJsonPath('codice', 'firma_assente');
    }

    public function test_invalid_contact_data_is_refused(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", ['firmatari' => ['cliente' => ['email' => 'non-una-mail']]])->assertStatus(422);
    }

    public function test_after_signing_the_same_id_returns_the_signed_document(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();
        $signature = $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", $this->contacts())->assertCreated()->json();

        $request = SignatureRequest::query()->findOrFail($signature['id']);
        app(SignatureProviderManager::class)->provider('fake')->completeAll($request->provider_ref);
        app(SignatureRequestService::class)->reconcile($request);

        $this->signedJson('GET', "{$base}/documenti/{$document['id']}/firma")->assertOk()->assertJsonPath('firmata', true)->assertJsonPath('stato', 'signed');
        $this->assertStringEndsWith('%FAKE-SIGNED', rtrim($this->signedJson('GET', "{$base}/documenti/{$document['id']}/file")->assertOk()->getContent()));
    }
}
