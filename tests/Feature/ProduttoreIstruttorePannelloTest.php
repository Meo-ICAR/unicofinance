<?php

namespace Tests\Feature;

use App\Filament\Pages\Simulazione;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;

class ProduttoreIstruttorePannelloTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function produttore(string $nome, string $piva): User
    {
        $utente = User::factory()->create(['role' => 'fornitore']);
        Fornitore::query()->create(['name' => $nome, 'piva' => $piva, 'is_active' => true, 'user_id' => $utente->getKey()]);

        return $utente;
    }

    public function test_a_producer_who_is_also_an_instructor_works_only_in_the_admin_panel(): void
    {
        Employee::query()->create(['name' => 'ZACCARIA LOREDANA', 'is_active' => true, 'employee_roles' => ['istruttore']]);
        $produttore = $this->produttore('Loredana  Zaccaria', '99900000031');

        $this->assertTrue($produttore->isProduttoreIstruttore());
        $this->assertFalse($produttore->canAccessPanel(Filament::getPanel('agenti')));
        $this->assertTrue($produttore->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_a_producer_who_is_an_employee_without_the_instructor_role_keeps_the_agents_portal(): void
    {
        Employee::query()->create(['name' => 'FUMMO BRIGIDA', 'is_active' => true, 'employee_roles' => ['dipendente']]);
        $produttore = $this->produttore('Brigida Fummo', '99900000032');

        $this->assertFalse($produttore->isProduttoreIstruttore());
        $this->assertTrue($produttore->canAccessPanel(Filament::getPanel('agenti')));
        $this->assertFalse($produttore->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_an_ordinary_producer_only_uses_the_agents_portal(): void
    {
        $produttore = $this->produttore('ZZ Agente Esterno', '99900000033');

        $this->assertTrue($produttore->canAccessPanel(Filament::getPanel('agenti')));
        $this->assertFalse($produttore->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_the_simulation_is_in_the_admin_panel_only_for_users_with_a_producer(): void
    {
        $produttore = $this->produttore('ZZ Agente Esterno', '99900000034');
        $staff = User::factory()->create(['role' => 'admin']);

        $this->actingAs($produttore);
        $this->assertTrue(Simulazione::canAccess());

        $this->actingAs($staff);
        $this->assertFalse(Simulazione::canAccess());
    }

    public function test_a_producer_who_is_also_an_instructor_runs_the_simulation_from_the_admin_panel(): void
    {
        Employee::query()->create(['name' => 'ZACCARIA LOREDANA', 'is_active' => true, 'employee_roles' => ['istruttore']]);
        $produttore = $this->produttore('Loredana Zaccaria', '99900000035');
        Filament::setCurrentPanel('admin');
        $this->actingAs($produttore);

        $this->get('/admin/simulazione')->assertOk();
        Livewire::test(Simulazione::class)->assertOk();
    }
}
