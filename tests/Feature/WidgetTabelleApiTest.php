<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\WidgetTabellaApiController;
use App\Models\Pratica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\PraticaStato;

class WidgetTabelleApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @var array<string, string> */
    private array $key = ['X-Api-Key' => 'chiave-prova'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['unico-core.bridge.api_key' => 'chiave-prova']);
    }

    public function test_every_widget_answers_with_its_table(): void
    {
        $agente = Fornitore::query()->create(['name' => 'ZZ Agente Widget', 'piva' => '99900000011', 'is_active' => true]);

        foreach (array_keys(WidgetTabellaApiController::WIDGET) as $widget) {
            $this->getJson("/api/widgets/{$widget}?fornitori_id={$agente->getKey()}", $this->key)
                ->assertOk()
                ->assertJsonPath('meta.widget', $widget)
                ->assertJsonPath('meta.count', 0);
        }

        $this->getJson('/api/widgets', $this->key)->assertOk()->assertJsonCount(count(WidgetTabellaApiController::WIDGET), 'widgets');
        $this->getJson('/api/widgets/inesistente', $this->key)->assertNotFound();
    }

    public function test_an_instructor_widget_is_global_or_limited_to_the_employee(): void
    {
        $mio = Employee::query()->create(['name' => 'Istruttore Mio', 'is_active' => true]);
        $altro = Employee::query()->create(['name' => 'Istruttore Altro', 'is_active' => true]);
        $stato = PraticaStato::query()->create(['codice' => 'ST-W', 'tipo_finanziamento' => 'Test', 'name' => 'Stato W', 'ordine' => 0, 'colore' => 'gray']);
        Pratica::query()->create(['codice_pratica' => 'W-MIA', 'employee_id' => $mio->getKey(), 'pratica_stato_id' => $stato->getKey(), 'upload_at' => now()]);
        Pratica::query()->create(['codice_pratica' => 'W-ALTRA', 'employee_id' => $altro->getKey(), 'pratica_stato_id' => $stato->getKey(), 'upload_at' => now()]);

        $this->getJson('/api/widgets/pratiche-in-corso', $this->key)->assertJsonPath('meta.count', 2);

        $this->getJson('/api/widgets/pratiche-in-corso?employee_id='.$mio->getKey(), $this->key)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.codice_pratica', 'W-MIA');
    }

    public function test_an_agent_widget_requires_the_agent_and_lists_only_his_practices(): void
    {
        $agente = Fornitore::query()->create(['name' => 'ZZ Agente A', 'piva' => '99900000012', 'is_active' => true]);
        $altro = Fornitore::query()->create(['name' => 'ZZ Agente B', 'piva' => '99900000013', 'is_active' => true]);
        $stato = PraticaStato::query()->create(['codice' => 'ST-A', 'tipo_finanziamento' => 'Test', 'name' => 'Stato A', 'ordine' => 0, 'colore' => 'gray']);
        Pratica::query()->create(['codice_pratica' => 'A-SUA', 'fornitori_id' => $agente->getKey(), 'pratica_stato_id' => $stato->getKey(), 'upload_at' => now()]);
        Pratica::query()->create(['codice_pratica' => 'A-ALTRUI', 'fornitori_id' => $altro->getKey(), 'pratica_stato_id' => $stato->getKey(), 'upload_at' => now()]);

        $this->getJson('/api/widgets/agente-pratiche-in-corso', $this->key)->assertUnprocessable();

        $this->getJson('/api/widgets/agente-pratiche-in-corso?fornitori_id='.$agente->getKey(), $this->key)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.codice_pratica', 'A-SUA');
    }

    public function test_a_wrong_key_is_refused(): void
    {
        $this->getJson('/api/widgets/pratiche-in-corso', ['X-Api-Key' => 'sbagliata'])->assertUnauthorized();
    }
}
