<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Pratiche\Pages\ListPratiche;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPratica;
use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

class PraticheAgenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_agents_panel_lists_only_the_impersonated_agents_practices(): void
    {
        Filament::setCurrentPanel('agenti');

        $staff = User::factory()->create(['role' => 'super_admin']);
        $agent = User::factory()->create(['role' => 'fornitore']);
        $otherAgent = User::factory()->create(['role' => 'fornitore']);
        $fornitore = Fornitore::query()->create([
            'name' => 'Produttore impersonato',
            'piva' => '999'.random_int(10000000, 99999999),
            'user_id' => $agent->id,
            'is_active' => true,
        ]);
        $other = Fornitore::query()->create([
            'name' => 'Altro produttore',
            'piva' => '998'.random_int(10000000, 99999999),
            'user_id' => $otherAgent->id,
            'is_active' => true,
        ]);
        $marker = 'ZZPRA'.Str::upper(Str::random(4));
        $own = Pratica::query()->create([
            'codice_pratica' => $marker.'-MIA',
            'cognome_cliente' => 'Rossi',
            'fornitori_id' => $fornitore->getKey(),
            'upload_at' => now(),
        ]);
        $foreign = Pratica::query()->create([
            'codice_pratica' => $marker.'-ALTRA',
            'cognome_cliente' => 'Bianchi',
            'fornitori_id' => $other->getKey(),
            'upload_at' => now(),
        ]);

        $this->actingAs($staff);
        app(AgentImpersonationService::class)->start($staff, $fornitore);

        $this->assertSame((int) $fornitore->getKey(), app(AgentImpersonationService::class)->agentFornitoreId());
        $this->assertSame($agent->id, auth()->id());

        $this->actingAs($agent);

        Livewire::test(ListPratiche::class)
            ->searchTable($marker)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);

        Livewire::test(ViewPratica::class, ['record' => $foreign->getKey()])
            ->assertNotFound();
    }
}
