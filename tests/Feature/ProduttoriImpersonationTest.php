<?php

namespace Tests\Feature;

use App\Filament\Resources\Produttori\Pages\ListProduttori;
use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;

class ProduttoriImpersonationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fornitore(array $attributes = []): Fornitore
    {
        return Fornitore::query()->create($attributes + [
            'name' => 'Produttore '.Str::upper(Str::random(4)),
            'piva' => '999'.random_int(10000000, 99999999),
            'email' => 'produttore.'.Str::lower(Str::random(6)).'@example.test',
            'is_active' => true,
        ]);
    }

    public function test_staff_sees_only_active_producers_and_can_enter_the_agents_panel(): void
    {
        Filament::setCurrentPanel('admin');

        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $marker = 'ZZ-TEST-'.Str::upper(Str::random(6));
        $active = $this->fornitore(['name' => $marker.' attivo', 'user_id' => $agent->id]);
        $withoutAccess = $this->fornitore(['name' => $marker.' senza', 'user_id' => null]);
        $inactiveAgent = User::factory()->create(['role' => 'fornitore']);
        $inactive = $this->fornitore([
            'name' => $marker.' inattivo',
            'user_id' => $inactiveAgent->id,
            'is_active' => false,
        ]);
        $ceasedAgent = User::factory()->create(['role' => 'fornitore']);
        $ceased = $this->fornitore([
            'name' => $marker.' cessato',
            'user_id' => $ceasedAgent->id,
            'dismissed_at' => now()->subDay()->toDateString(),
        ]);
        $endingLater = $this->fornitore([
            'name' => $marker.' futuro',
            'dismissed_at' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($staff);

        Livewire::test(ListProduttori::class)
            ->searchTable($marker)
            ->assertCanSeeTableRecords([$active, $withoutAccess, $endingLater])
            ->assertCanNotSeeTableRecords([$inactive, $ceased])
            ->assertActionVisible(TestAction::make('impersonateFornitore')->table($active))
            ->assertActionHidden(TestAction::make('impersonateFornitore')->table($withoutAccess))
            ->callAction(TestAction::make('impersonateFornitore')->table($active))
            ->assertRedirect(Filament::getPanel('agenti')->getUrl());

        $this->assertSame($agent->id, auth()->id());
        $this->assertTrue(app(AgentImpersonationService::class)->isImpersonating());
        $this->assertTrue($agent->canAccessPanel(Filament::getPanel('agenti')));
    }

    public function test_leave_restores_the_staff_user_on_the_producers_list(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = $this->fornitore(['user_id' => $agent->id]);

        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);

        $response = $this->post(route('agenti.impersonation.leave'));

        $response->assertRedirect(Filament::getPanel('admin')->getUrl().'/produttori');
        $this->assertSame($staff->id, auth()->id());
        $this->assertFalse(app(AgentImpersonationService::class)->isImpersonating());
    }

    public function test_a_producer_cannot_impersonate_or_open_the_admin_list(): void
    {
        Filament::setCurrentPanel('admin');

        $producer = User::factory()->create(['role' => 'fornitore']);
        $other = User::factory()->create(['role' => 'fornitore']);
        $fornitore = $this->fornitore(['user_id' => $other->id]);
        $this->fornitore(['user_id' => $producer->id]);

        $this->actingAs($producer);

        $this->assertFalse($producer->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse(app(AgentImpersonationService::class)->canImpersonate($producer, $fornitore));
        $this->get('/admin/produttori')->assertForbidden();
    }

    public function test_leave_is_forbidden_when_not_impersonating(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post(route('agenti.impersonation.leave'))->assertForbidden();
    }

    public function test_inactive_producer_cannot_be_impersonated(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = $this->fornitore(['user_id' => $agent->id, 'is_active' => false]);
        $ceased = $this->fornitore([
            'user_id' => $agent->id,
            'dismissed_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($staff);

        $service = app(AgentImpersonationService::class);
        $this->assertFalse($service->canImpersonate($staff, $fornitore));
        $this->assertFalse($service->canImpersonate($staff, $ceased));
    }
}
