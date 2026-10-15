<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\AddressesRelationManager;
use App\Filament\RelationManagers\SociRelationManager;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Pratica;

class ClienteIstruttoriaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_a_person_shows_quota_cedibile_when_the_latest_practice_is_a_delega(): void
    {
        $client = $this->clientWithPractices(['Mutuo prima casa' => '2025-01-01', 'Delega di pagamento' => '2026-05-01']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldIsVisible('salary_quote')
            ->assertFormFieldIsVisible('salary')
            ->assertFormFieldIsVisible('is_pep');
    }

    public function test_a_person_hides_quota_cedibile_when_the_latest_practice_is_a_mutuo(): void
    {
        $client = $this->clientWithPractices(['Delega di pagamento' => '2025-01-01', 'Mutuo prima casa' => '2026-05-01']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldIsHidden('salary_quote')
            ->assertFormFieldIsVisible('salary');
    }

    public function test_a_company_has_no_verification_tab_and_shows_company_data(): void
    {
        $client = Client::query()->create(['name' => 'Acme Srl', 'is_person' => false, 'tax_code' => '12345678903']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldIsVisible('legal_form')
            ->assertFormFieldIsHidden('is_pep');
    }

    public function test_the_client_resource_attaches_documents_and_addresses(): void
    {
        $this->assertSame(
            [DocumentsRelationManager::class, SociRelationManager::class, AddressesRelationManager::class],
            ClientResource::getRelations(),
        );
    }

    /**
     * @param  array<string, string>  $products  prodotto => data inserimento
     */
    private function clientWithPractices(array $products): Client
    {
        $client = Client::query()->create(['name' => 'Rossi', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);

        foreach ($products as $product => $date) {
            Pratica::query()->create(['codice_pratica' => 'P-'.$date, 'codice_fiscale' => 'RSSMRA80A01H501U', 'denominazione_prodotto' => $product, 'data_inserimento_pratica' => $date, 'upload_at' => now()]);
        }

        return $client;
    }
}
