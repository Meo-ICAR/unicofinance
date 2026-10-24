<?php

namespace Tests\Feature;

use App\Models\Pratica;
use App\Services\Istruttoria\IstruttoreDelProduttore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;

class IstruttoreDelProduttoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function pratica(string $codice, ?string $agente, ?Employee $istruttore = null, ?Fornitore $fornitore = null): Pratica
    {
        return Pratica::query()->create(['codice_pratica' => $codice, 'denominazione_agente' => $agente, 'fornitori_id' => $fornitore?->getKey(), 'employee_id' => $istruttore?->getKey(), 'upload_at' => now()]);
    }

    public function test_a_practice_of_one_of_the_listed_producers_gets_the_producer_as_instructor_even_if_another_was_set(): void
    {
        $fummo = Employee::query()->create(['name' => 'FUMMO BRIGIDA', 'is_active' => true]);
        $zaccaria = Employee::query()->create(['name' => 'ZACCARIA  LOREDANA', 'is_active' => true]);
        $altro = Employee::query()->create(['name' => 'Altro Istruttore', 'is_active' => true]);
        $produttore = Fornitore::query()->create(['name' => 'Brigida Fummo', 'piva' => '99900000051', 'is_active' => true]);

        $perFornitore = $this->pratica('FRZ-1', null, $altro, $produttore);
        $perNome = $this->pratica('FRZ-2', 'ZACCARIA LOREDANA');
        $altrui = $this->pratica('FRZ-3', 'Un Altro Agente', $altro);

        $servizio = app(IstruttoreDelProduttore::class);

        $this->assertTrue($servizio->forza($perFornitore));
        $this->assertTrue($servizio->forza($perNome));
        $this->assertFalse($servizio->forza($altrui));
        $this->assertFalse($servizio->forza($perNome->fresh()), 'Già forzata: nessun cambio.');

        $this->assertSame($fummo->getKey(), $perFornitore->fresh()->employee_id);
        $this->assertSame($zaccaria->getKey(), $perNome->fresh()->employee_id);
        $this->assertSame($altro->getKey(), $altrui->fresh()->employee_id);
    }

    public function test_the_batch_changes_only_the_practices_that_need_it(): void
    {
        $fummo = Employee::query()->create(['name' => 'FUMMO BRIGIDA', 'is_active' => true]);
        $this->pratica('FRZ-1', 'FUMMO BRIGIDA');
        $this->pratica('FRZ-2', 'FUMMO BRIGIDA', $fummo);
        $this->pratica('FRZ-3', 'Qualcun Altro');

        $this->assertSame(1, app(IstruttoreDelProduttore::class)->forzaTutte());
        $this->assertSame(0, app(IstruttoreDelProduttore::class)->forzaTutte(), 'Ripetibile.');
        $this->artisan('pratiche:forza-istruttori-produttore')->expectsOutputToContain('0')->assertSuccessful();
    }

    public function test_without_the_employee_nothing_changes(): void
    {
        $pratica = $this->pratica('FRZ-1', 'FUMMO BRIGIDA');

        $this->assertFalse(app(IstruttoreDelProduttore::class)->forza($pratica));
        $this->assertNull($pratica->fresh()->employee_id);
    }
}
