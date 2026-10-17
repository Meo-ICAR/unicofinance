<?php

namespace Tests\Feature;

use App\Filament\Resources\Istituti\Pages\EditIstituto;
use App\Filament\Resources\Istituti\RelationManagers\PortaliIstitutoRelationManager;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Pratica;
use App\Models\User;
use App\Services\PortaleBanca;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Website;

class IstitutoPortaliTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function staff(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_the_institute_has_one_portal_per_type_and_the_api_secret_is_stored_encrypted(): void
    {
        $this->staff();
        $banca = Cliente::query()->create(['name' => 'Banca Portali']);
        $gestore = fn () => Livewire::test(PortaliIstitutoRelationManager::class, ['ownerRecord' => $banca, 'pageClass' => EditIstituto::class]);

        $gestore()->callTableAction('create', data: ['type' => Website::PORTAL_CARICAMENTO, 'url' => 'https://pratiche.banca.test/inserimento'])->assertHasNoTableActionErrors();
        $gestore()->callTableAction('create', data: ['type' => Website::PORTAL_CARICAMENTO, 'url' => 'https://altro.banca.test'])->assertHasTableActionErrors(['type']);
        $gestore()->callTableAction('create', data: ['type' => Website::PORTAL_API, 'url' => 'https://api.banca.test', 'api_username' => 'mediatore', 'api_secret' => 'segreto-123'])->assertHasNoTableActionErrors();

        $api = $banca->websites()->where('type', Website::PORTAL_API)->firstOrFail();
        $this->assertSame('segreto-123', $api->api_secret);
        $this->assertNotSame('segreto-123', $api->getRawOriginal('api_secret'));
        $this->assertSame('api.banca.test', $api->domain);
        $this->assertSame(['api', 'caricamento'], $banca->websites()->orderBy('type')->pluck('type')->all());
    }

    public function test_the_practice_links_the_loading_portal_before_the_old_address(): void
    {
        $this->staff();
        $banca = Cliente::query()->create(['name' => 'Banca Link', 'abi' => '03069', 'is_active' => true, 'portal_url' => 'https://vecchio.banca.test']);
        $this->assertSame('https://vecchio.banca.test', PortaleBanca::url($banca->getKey()));

        $banca->websites()->create(['type' => Website::PORTAL_MODULISTICA, 'url' => 'https://moduli.banca.test']);
        $this->assertSame('https://vecchio.banca.test', PortaleBanca::url($banca->getKey()), 'La modulistica non è il portale di caricamento.');

        $banca->websites()->create(['type' => Website::PORTAL_CARICAMENTO, 'url' => 'https://pratiche.banca.test']);
        $this->assertSame('https://pratiche.banca.test', PortaleBanca::url($banca->getKey()));

        $banca->websites()->where('type', Website::PORTAL_CARICAMENTO)->update(['is_active' => false]);
        $this->assertSame('https://vecchio.banca.test', PortaleBanca::url($banca->getKey()), 'Un portale disattivato non si propone.');

        $banca->websites()->where('type', Website::PORTAL_CARICAMENTO)->update(['is_active' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZPRT-'.Str::upper(Str::random(4)), 'cognome_cliente' => 'Rossi', 'clienti_id' => $banca->getKey(), 'upload_at' => now()]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSeeHtml('href="https://pratiche.banca.test"');
    }
}
