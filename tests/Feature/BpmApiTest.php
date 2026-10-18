<?php

namespace Tests\Feature;

use App\Models\BlacklistClienteFornitore;
use App\Models\Pratica;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Unico\Core\Bridge\Jobs\RunBridgeCommand;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Fornitore;

/** API che unicobpm chiama su unicofinance (prima su unicoloan): pratica, campi dei modelli, scrittura di fine processo, utenti, comandi. */
class BpmApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @var array<string, string> */
    private array $key = ['X-Api-Key' => 'chiave-prova'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['unico-core.bridge.api_key' => 'chiave-prova']);
    }

    /** @return array{0: Pratica, 1: Fornitore, 2: Cliente} */
    private function pratica(): array
    {
        $agente = Fornitore::query()->create(['name' => 'ZZ Agente BPM', 'piva' => '99900000002', 'is_active' => true]);
        $banca = Cliente::query()->create(['name' => 'ZZ Banca BPM']);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZBPM-1', 'fornitori_id' => $agente->getKey(), 'clienti_id' => $banca->getKey(), 'upload_at' => now()]);

        return [$pratica, $agente, $banca];
    }

    /**
     * La blacklist è una tabella dell'app (connessione mysql); nelle prove, con sqlite come default, il modello legge il core:
     * la si crea lì come tabella temporanea, che non chiude la transazione della prova.
     */
    private function tabellaBlacklist(): void
    {
        if (Schema::connection('core')->hasTable('blacklist_clienti_fornitori')) {
            return;
        }

        Schema::connection('core')->create('blacklist_clienti_fornitori', function (Blueprint $table): void {
            $table->temporary();
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('fornitore_id');
            $table->date('data_inizio')->nullable();
            $table->date('data_fine')->nullable();
            $table->timestamps();
        });
    }

    public function test_without_the_shared_key_the_apis_answer_401(): void
    {
        $this->getJson('/api/pratiche/1')->assertUnauthorized();
        $this->getJson('/api/models/pratica/fields', ['X-Api-Key' => 'sbagliata'])->assertUnauthorized();
        $this->getJson('/api/users/lookup?email=a@example.com')->assertUnauthorized();
    }

    public function test_the_practice_reports_state_documents_and_agent_blacklist(): void
    {
        [$pratica, $agente, $banca] = $this->pratica();
        $this->tabellaBlacklist();

        $this->getJson('/api/pratiche/'.$pratica->getKey(), $this->key)->assertOk()
            ->assertJsonPath('codice_pratica', 'ZZBPM-1')
            ->assertJsonPath('fornitori_id', $agente->getKey())
            ->assertJsonPath('agente_blacklistato', false)
            ->assertJsonStructure(['stato_pratica', 'documenti' => ['obbligatori', 'presenti', 'mancanti']]);

        BlacklistClienteFornitore::query()->create(['cliente_id' => $banca->getKey(), 'fornitore_id' => $agente->getKey(), 'data_inizio' => today()->subDay()]);

        $this->getJson('/api/pratiche/'.$pratica->getKey(), $this->key)->assertOk()->assertJsonPath('agente_blacklistato', true);
        $this->getJson('/api/pratiche/999999999', $this->key)->assertNotFound()->assertJson(['message' => 'Pratica non trovata.']);
    }

    public function test_model_fields_list_columns_and_belongs_to_lookups(): void
    {
        $this->getJson('/api/models/fornitore/fields', $this->key)->assertOk()
            ->assertJsonStructure(['model', 'columns', 'lookups'])
            ->assertJsonFragment(['name' => 'stipulated_at']);

        $this->getJson('/api/models/pratica/fields', $this->key)->assertOk()
            ->assertJsonFragment(['relation' => 'fornitori', 'foreign_key' => 'fornitori_id', 'owner_key' => 'id', 'related_model' => 'fornitore']);

        $this->getJson('/api/models/modello-inesistente/fields', $this->key)->assertNotFound();
    }

    public function test_a_field_is_written_at_the_end_of_a_process(): void
    {
        [, $agente] = $this->pratica();

        $this->patchJson('/api/models/fornitore/'.$agente->getKey(), ['field' => 'stipulated_at', 'value' => '2026-10-01'], $this->key)
            ->assertOk()->assertJsonPath('field', 'stipulated_at');

        $this->assertSame('2026-10-01', substr((string) $agente->fresh()->stipulated_at, 0, 10));
    }

    public function test_writes_on_unknown_or_protected_fields_are_refused(): void
    {
        [$pratica, $agente] = $this->pratica();

        $this->patchJson('/api/models/fornitore/'.$agente->getKey(), ['field' => 'campo_inesistente', 'value' => 'x'], $this->key)->assertStatus(422);
        $this->patchJson('/api/models/pratica/'.$pratica->getKey(), ['field' => 'pratica_stato_id', 'value' => 1], $this->key)->assertStatus(422);
        $this->patchJson('/api/models/fornitore/'.$agente->getKey(), ['field' => 'id', 'value' => 1], $this->key)->assertStatus(422);
        $this->patchJson('/api/models/pratica/'.$pratica->getKey(), ['field' => 'approved_at', 'value' => '2026-10-01'], $this->key)->assertStatus(422);
        $this->patchJson('/api/models/fornitore/'.$agente->getKey(), ['field' => 'description'], $this->key)->assertStatus(422);
        $this->patchJson('/api/models/modello-inesistente/1', ['field' => 'name', 'value' => 'x'], $this->key)->assertNotFound();
        $this->patchJson('/api/models/fornitore/999999999', ['field' => 'description', 'value' => 'x'], $this->key)->assertNotFound()->assertJson(['message' => 'Record non trovato.']);
    }

    public function test_user_lookup_reports_only_users_of_the_admin_panel(): void
    {
        User::factory()->create(['email' => 'istruttore@example.com', 'role' => 'super_admin']);

        $this->getJson('/api/users/lookup?email=istruttore@example.com', $this->key)->assertOk()->assertJson(['exists' => true]);
        $this->getJson('/api/users/lookup?email=nessuno@example.com', $this->key)->assertOk()->assertJson(['exists' => false]);
    }

    public function test_commands_are_listed_and_queued_with_the_shared_key_only(): void
    {
        Queue::fake();

        $this->getJson('/api/commands', $this->key)->assertOk()->assertJsonStructure(['commands' => ['unico-core:documents-remind', 'signature:sync']]);
        $run = $this->postJson('/api/commands/unico-core:documents-remind', ['options' => ['force' => true]], $this->key)->assertAccepted()->json('run_id');
        $this->postJson('/api/commands/unico-core:documents-remind', ['options' => ['altro' => 1]], $this->key)->assertStatus(422);
        $this->postJson('/api/commands/migrate:fresh', [], $this->key)->assertNotFound();
        $this->postJson('/api/commands/signature:sync')->assertUnauthorized();

        Queue::assertPushed(RunBridgeCommand::class, 1);
        $this->getJson("/api/commands/runs/{$run}", $this->key)->assertOk()->assertJsonPath('status', 'queued');
    }

    public function test_the_bpm_landing_logs_in_with_a_verified_token_only(): void
    {
        config(['services.bpm.url' => 'https://bpm.example.com']);
        $user = User::factory()->create(['email' => 'istruttore@example.com', 'role' => 'super_admin']);

        Http::fake(['bpm.example.com/api/verify-token' => Http::sequence()->push(['valid' => false])->push(['valid' => true])]);
        $this->get('/bpm-landing/42?token=scaduto&user_email=istruttore@example.com')->assertForbidden();
        $this->assertGuest();

        $this->get('/bpm-landing/42?token=valido&user_email=istruttore@example.com')->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_landing_accepts_the_token_in_a_post_and_leaves_no_referrer_nor_cache(): void
    {
        config(['services.bpm.url' => 'https://bpm.example.com']);
        $user = User::factory()->create(['email' => 'istruttore@example.com', 'role' => 'super_admin']);
        Http::fake(['bpm.example.com/api/verify-token' => Http::response(['valid' => true])]);

        $this->post('/bpm-landing/dashboard', ['token' => 'valido', 'user_email' => 'istruttore@example.com'])
            ->assertRedirect('/admin')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeaderContains('Cache-Control', 'no-store');

        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn ($request) => $request['token'] === 'valido');
    }
}
