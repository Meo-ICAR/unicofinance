<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeTypes\Pages\ViewEmployeeType;
use App\Filament\Resources\EmployeeTypes\RelationManagers\PermissionsRelationManager;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\EmployeeType;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Resource;

class RuoliEPermessiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_an_active_producer_gets_the_fornitore_role_by_default(): void
    {
        $attivo = Fornitore::query()->create(['name' => 'Attivo', 'is_active' => true]);
        $inattivo = Fornitore::query()->create(['name' => 'Inattivo', 'is_active' => false]);

        $this->assertSame(['fornitore'], $attivo->fresh()->employee_roles);
        $this->assertEmpty($inattivo->fresh()->employee_roles);
    }

    public function test_the_matrix_grants_a_permission_in_the_database(): void
    {
        Filament::setCurrentPanel('admin');
        $risorsa = Resource::query()->firstOrCreate(['app_name' => config('app.name'), 'key' => 'chiamate-api'], ['name' => 'Chiamate API', 'min_plan' => 'BASE']);
        $ruolo = EmployeeType::query()->create(['name' => 'ruolo-prova', 'companytype' => 'FINANCE']);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        Livewire::test(PermissionsRelationManager::class, ['ownerRecord' => $ruolo, 'pageClass' => ViewEmployeeType::class])
            ->assertCanSeeTableRecords([$risorsa])
            ->call('updateTableColumnState', 'perm_viewAny', $risorsa->getKey(), true);

        $this->assertTrue($ruolo->employeeTypePermissions()->where('resource_id', $risorsa->getKey())->where('action', 'viewAny')->exists());
        $this->assertFalse($ruolo->employeeTypePermissions()->where('action', 'delete')->exists());
    }
}
