<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Pratica;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Client;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\PraticaClient;
use Unico\Core\Models\Task;

class PraticaDocumentiClientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_activating_a_plico_on_a_practice_also_creates_the_client_documents_and_the_practice_lists_them_all(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $task = Task::query()->create(['name' => 'Plico prova clienti', 'taskable' => 'pratica', 'is_active' => true]);
        $contratto = DocumentType::query()->create(['name' => 'Contratto prova', 'code' => 'CTP']);
        $identita = DocumentType::query()->create(['name' => 'Identità prova', 'code' => 'IDP', 'client_scope' => 'persona_fisica']);
        DocumentRequirement::query()->create(['task_id' => $task->id, 'document_type_id' => $contratto->id]);
        DocumentRequirement::query()->create(['task_id' => $task->id, 'document_type_id' => $identita->id, 'documentable' => 'client', 'client_roles' => ['richiedente', 'coobbligato']]);

        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZ-DOC-1', 'upload_at' => now()]);
        $richiedente = Client::query()->create(['name' => 'Rossi', 'is_person' => true]);
        $coobbligato = Client::query()->create(['name' => 'Verdi', 'is_person' => true]);
        $garante = Client::query()->create(['name' => 'Neri', 'is_person' => true]);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $richiedente->id, 'role' => 'richiedente']);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $coobbligato->id, 'role' => 'coobbligato']);
        PraticaClient::query()->create(['pratica_id' => $pratica->getKey(), 'client_id' => $garante->id, 'role' => 'garante']);
        $altroDocumento = Document::query()->create(['documentable_type' => 'client', 'documentable_id' => (string) $garante->id, 'document_type_id' => $identita->id, 'name' => 'Identità del garante, non richiesta']);

        $manager = fn () => Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class]);

        $manager()->callAction(TestAction::make('generaPlico')->table(), ['plichi' => [$task->id]])->assertNotified();

        $documenti = Document::query()->where('document_requirement_id', '!=', null)->get();
        $this->assertCount(3, $documenti, 'contratto sulla pratica, identità di richiedente e coobbligato');
        $this->assertSame(0, $documenti->where('documentable_id', (string) $garante->id)->count());

        $manager()
            ->loadTable()
            ->assertCanSeeTableRecords($documenti)
            ->assertCanNotSeeTableRecords([$altroDocumento])
            ->assertSee('Rossi · richiedente')
            ->assertSee('Verdi · coobbligato');
    }
}
