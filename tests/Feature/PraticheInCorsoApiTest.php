<?php

namespace Tests\Feature;

use App\Models\Pratica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Employee;
use Unico\Core\Models\PraticaStato;

class PraticheInCorsoApiTest extends TestCase
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

    /**
     * @param  array<string, bool>  $flags
     */
    private function pratica(string $codice, array $flags = [], ?Employee $istruttore = null): Pratica
    {
        $stato = PraticaStato::query()->create(['codice' => 'ST-'.$codice, 'tipo_finanziamento' => 'Test', 'name' => 'Stato '.$codice, 'ordine' => 0, 'colore' => 'gray', 'is_working' => true, ...$flags]);

        return Pratica::query()->create(['codice_pratica' => $codice, 'cognome_cliente' => 'Rossi', 'pratica_stato_id' => $stato->getKey(), 'employee_id' => $istruttore?->getKey(), 'upload_at' => now()]);
    }

    public function test_the_table_lists_only_practices_in_progress(): void
    {
        $this->pratica('CORSO-1');
        $this->pratica('CORSO-NO-LAVORAZIONE', ['is_working' => false]);
        $this->pratica('CORSO-NO-APPROVATA', ['is_approved' => true]);
        $this->pratica('CORSO-NO-RESPINTA', ['is_rejected' => true]);
        $this->pratica('CORSO-NO-SOSPESA', ['is_suspended' => true]);

        $this->getJson('/api/pratiche/in-corso', $this->key)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.codice_pratica', 'CORSO-1')
            ->assertJsonPath('data.0.stato_pratica', 'Stato CORSO-1');
    }

    public function test_the_employee_parameter_limits_the_table_to_that_instructor(): void
    {
        $mio = Employee::query()->create(['name' => 'Istruttore Mio', 'is_active' => true]);
        $altro = Employee::query()->create(['name' => 'Istruttore Altro', 'is_active' => true]);
        $this->pratica('MIA-1', [], $mio);
        $this->pratica('ALTRA-1', [], $altro);

        $this->getJson('/api/pratiche/in-corso', $this->key)->assertJsonPath('meta.count', 2);

        $this->getJson('/api/pratiche/in-corso?employee_id='.$mio->getKey(), $this->key)
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.employee_id', $mio->getKey())
            ->assertJsonPath('data.0.codice_pratica', 'MIA-1')
            ->assertJsonPath('data.0.istruttore', 'Istruttore Mio');
    }

    public function test_an_unknown_instructor_or_a_wrong_key_is_refused(): void
    {
        $this->getJson('/api/pratiche/in-corso?employee_id=999999', $this->key)->assertUnprocessable();
        $this->getJson('/api/pratiche/in-corso', ['X-Api-Key' => 'sbagliata'])->assertUnauthorized();
    }
}
