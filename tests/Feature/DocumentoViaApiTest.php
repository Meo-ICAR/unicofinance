<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentType;

class DocumentoViaApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    private function documento(?string $service): array
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $societa = Client::query()->create(['name' => 'Acme Srl', 'vat_number' => '12345678903', 'tax_code' => '12345678903', 'is_person' => false]);
        $tipo = DocumentType::query()->create(['name' => 'Visura di prova', 'code' => 'VIS-T', 'api_service' => $service, 'is_client' => true]);
        $documento = Document::query()->create(['documentable_type' => 'client', 'documentable_id' => (string) $societa->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'Visura', 'status' => DocumentStatus::PENDING]);

        return [$societa, $documento];
    }

    public function test_a_document_with_an_api_service_is_requested_and_the_call_is_logged(): void
    {
        Storage::fake('public');
        config(['services.openapi.token' => 'token-di-prova']);
        Http::fake(['*IT-advanced*' => Http::response(['success' => true, 'data' => [['companyName' => 'ACME S.R.L.', 'vatCode' => '12345678903', 'shareHolders' => [['companyName' => 'Socio Spa', 'taxCode' => '01234567897']]]]])]);
        [$societa, $documento] = $this->documento('openapi_company');

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $societa, 'pageClass' => EditClient::class])
            ->callAction(TestAction::make('richiediApi')->table($documento))
            ->assertNotified();

        $documento->refresh();
        $this->assertSame(DocumentStatus::UPLOADED, $documento->status);
        $this->assertSame('dati-societari-12345678903.json', $documento->getFirstMedia('documents')->file_name);
        $this->assertSame('openapi_company', $documento->metadata['api']['service']);
        $this->assertSame(1, ApiCall::query()->where('operation', 'company_advanced')->count());
    }

    public function test_a_failing_api_leaves_the_document_to_upload(): void
    {
        config(['services.openapi.token' => 'token-di-prova']);
        Http::fake(['*IT-advanced*' => Http::response(['success' => false, 'message' => 'non trovata'], 404)]);
        [$societa, $documento] = $this->documento('openapi_company');

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $societa, 'pageClass' => EditClient::class])
            ->callAction(TestAction::make('richiediApi')->table($documento))
            ->assertNotified();

        $this->assertSame(DocumentStatus::PENDING, $documento->refresh()->status);
        $this->assertNull($documento->getFirstMedia('documents'));
    }

    public function test_the_request_action_only_exists_for_types_with_a_service(): void
    {
        [$societa, $documento] = $this->documento(null);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $societa, 'pageClass' => EditClient::class])
            ->assertActionHidden(TestAction::make('richiediApi')->table($documento));
    }
}
