<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\SociRelationManager;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClienteSociTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_the_partners_manager_is_only_for_companies_and_creates_the_person(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $societa = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false]);
        $persona = Client::query()->create(['name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U', 'is_person' => true]);

        $this->assertTrue(SociRelationManager::canViewForRecord($societa, EditClient::class));
        $this->assertFalse(SociRelationManager::canViewForRecord($persona, EditClient::class));

        Livewire::test(SociRelationManager::class, ['ownerRecord' => $societa, 'pageClass' => EditClient::class])
            ->callTableAction('create', data: ['cognome' => 'Bianchi', 'nome' => 'Anna', 'codice_fiscale' => 'bncnna82c45h501y', 'role' => 'titolare_effettivo', 'shares_percentage' => 60])
            ->assertHasNoTableActionErrors();

        $socio = Client::query()->where('tax_code', 'BNCNNA82C45H501Y')->firstOrFail();
        $this->assertTrue($socio->is_person);
        $relazione = ClientRelation::query()->where('company_id', $societa->getKey())->firstOrFail();
        $this->assertSame($socio->getKey(), $relazione->client_id);
        $this->assertSame('titolare_effettivo', $relazione->role);
    }

    public function test_the_company_has_the_verifications_tab_without_the_pep_flag(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $societa = Client::query()->create(['name' => 'Acme Srl', 'tax_code' => '12345678903', 'is_person' => false]);

        Livewire::test(EditClient::class, ['record' => $societa->getKey()])
            ->assertSee('Verifiche')
            ->assertFormFieldExists('is_sanctioned')
            ->assertFormFieldHidden('is_pep');
    }
}
