<?php

namespace Tests\Feature;

use App\Models\AgentApiClient;
use App\Models\Pratica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @var array{token: string, secret: string} */
    private array $issued;

    protected function setUp(): void
    {
        parent::setUp();

        $this->issued = ['token' => 'tok-di-prova', 'secret' => 'SEG'];
        AgentApiClient::query()->create([
            'name' => 'unicoagent',
            'token_hash' => hash('sha256', $this->issued['token']),
            'secret' => $this->issued['secret'],
        ]);
    }

    public function test_una_richiesta_firmata_crea_la_pratica_ed_e_idempotente(): void
    {
        $this->agente();
        $body = json_encode($this->payload());

        $this->signed('POST', '/api/agente/v1/richieste', $body)
            ->assertCreated()
            ->assertJson(['codice_pratica' => 'WA-FIN-2026-0300']);

        $this->assertSame(1, Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0300')->count());
        $this->assertSame('RSSMRA80A01H501U', Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0300')->value('codice_fiscale'));

        $this->signed('POST', '/api/agente/v1/richieste', $body)->assertOk()->assertJson(['codice_pratica' => 'WA-FIN-2026-0300']);
        $this->assertSame(1, Pratica::query()->where('codice_pratica', 'WA-FIN-2026-0300')->count());
    }

    public function test_firma_segreto_orario_e_percorso_vengono_controllati(): void
    {
        $path = '/api/agente/v1/richieste/FIN-2026-0300/moduli';
        $auth = ['Authorization' => 'Bearer '.$this->issued['token'], 'Accept' => 'application/json'];

        $this->getJson($path, $auth + $this->signature('GET', $path, hash('sha256', ''), null, 'segreto-sbagliato'))->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', $path, hash('sha256', ''), time() - 3600))->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', '/api/agente/v1/altro', hash('sha256', '')))->assertUnauthorized();
    }

    public function test_un_cliente_di_un_altro_agente_viene_rifiutato(): void
    {
        $mio = $this->agente('12345678901');
        $altro = $this->agente('10987654321');
        Pratica::query()->create([
            'codice_pratica' => 'GIA-1',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'fornitori_id' => $altro->getKey(),
            'upload_at' => now(),
        ]);

        $this->signed('POST', '/api/agente/v1/richieste', json_encode($this->payload()))
            ->assertStatus(409)
            ->assertJson(['codice' => 'cliente_di_altro_agente']);

        $this->assertNull(Pratica::query()->where('fornitori_id', $mio->getKey())->where('codice_pratica', 'WA-FIN-2026-0300')->first());
    }

    public function test_il_documento_si_accetta_solo_se_l_hash_coincide(): void
    {
        $this->agente();
        $this->signed('POST', '/api/agente/v1/richieste', json_encode($this->payload()))->assertCreated();

        $contents = '%PDF-1.4 identita';
        $file = UploadedFile::fake()->createWithContent('identita.pdf', $contents);
        $hash = hash_file('sha256', $file->getRealPath());
        $path = '/api/agente/v1/richieste/FIN-2026-0300/documenti';

        $this->upload($path, $file, $hash, ['tipo' => 'documento_identita', 'id_origine' => '9', 'analisi' => '{"esito":"verificato"}'])
            ->assertCreated()
            ->assertJsonStructure(['id']);

        $declared = hash('sha256', 'altro contenuto');
        $this->upload($path, $file, $declared, ['tipo' => 'documento_identita'])->assertStatus(422)->assertJson(['codice' => 'hash_non_coincide']);
    }

    public function test_il_comando_conserva_solo_l_hash_del_token(): void
    {
        Artisan::call('agent-api:client', ['name' => 'altro']);
        $row = AgentApiClient::query()->where('name', 'altro')->first();

        $this->assertNotNull($row);
        $this->assertNotSame($this->issued['token'], $row->token_hash);
        $this->assertSame(64, strlen($row->token_hash));
    }

    private function signed(string $method, string $path, string $body): \Illuminate\Testing\TestResponse
    {
        $server = $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->issued['token'],
        ] + $this->signature($method, $path, hash('sha256', $body)));

        return $this->call($method, $path, [], [], [], $server, $body);
    }

    private function upload(string $path, UploadedFile $file, string $hash, array $fields): \Illuminate\Testing\TestResponse
    {
        $server = $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->issued['token'],
            'X-Content-Sha256' => $hash,
        ] + $this->signature('POST', $path, $hash));

        return $this->call('POST', $path, $fields, [], ['file' => $file], $server);
    }

    /** @return array<string, string> */
    private function signature(string $method, string $path, string $contentHash, ?int $timestamp = null, ?string $secret = null): array
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', implode('.', [$timestamp, $method, $path, $contentHash]), $secret ?? $this->issued['secret']);

        return ['X-Timestamp' => (string) $timestamp, 'X-Signature' => $signature];
    }

    private function agente(string $piva = '12345678901'): Fornitore
    {
        return Fornitore::query()->create([
            'name' => 'Agente '.$piva,
            'piva' => $piva,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'riferimento' => 'FIN-2026-0300',
            'prodotto' => 'personale',
            'prodotto_etichetta' => 'Prestito personale',
            'pratica' => ['importo_richiesto' => 5000, 'durata_mesi' => 36],
            'cliente' => [
                'cognome' => 'Rossi',
                'nome' => 'Mario',
                'codice_fiscale' => 'RSSMRA80A01H501U',
                'telefono' => '+393331234567',
                'email' => 'mario@example.com',
            ],
            'agente' => ['nome' => 'Agente', 'partita_iva' => '12345678901'],
            'consenso' => ['contatto_diretto' => true],
        ];
    }
}
