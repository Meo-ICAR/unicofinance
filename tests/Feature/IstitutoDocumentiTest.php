<?php

namespace Tests\Feature;

use App\Filament\Resources\Istituti\Pages\ViewIstituto;
use App\Filament\Resources\Istituti\RelationManagers\DocumentiIstitutoRelationManager;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Documents\BankDocumentRequirements;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Task;

class IstitutoDocumentiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    public function test_a_bank_document_is_attached_to_the_product_tasks_and_only_asked_for_that_bank(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $banca = Cliente::query()->create(['name' => 'Banca Test']);
        $altra = Cliente::query()->create(['name' => 'Altra Banca']);

        Livewire::test(DocumentiIstitutoRelationManager::class, ['ownerRecord' => $banca, 'pageClass' => ViewIstituto::class])
            ->callTableAction('create', data: ['name' => 'Modulo mutuo Banca Test', 'prodotti' => ['Mutuo'], 'fase' => BankDocumentRequirements::CHECKLIST, 'is_signed' => false])
            ->assertHasNoTableActionErrors();

        $type = DocumentType::query()->where('name', 'Modulo mutuo Banca Test')->firstOrFail();
        $this->assertSame($banca->getKey(), $type->clienti_id);

        $tasks = DocumentRequirement::query()->where('document_type_id', $type->getKey())->pluck('task_id');
        $this->assertGreaterThan(0, $tasks->count());
        $this->assertTrue(Task::query()->whereIn('id', $tasks)->where('trigger_value', 'Mutuo')->count() === $tasks->count());
        $this->assertSame(['Mutuo'], app(BankDocumentRequirements::class)->prodotti($type)['prodotti']);

        $conBanca = app(PracticeTaskDocuments::class)->checklist('Mutuo', 'privati', $banca->getKey())->pluck('document_type_id');
        $conAltra = app(PracticeTaskDocuments::class)->checklist('Mutuo', 'privati', $altra->getKey())->pluck('document_type_id');
        $this->assertTrue($conBanca->contains($type->getKey()));
        $this->assertFalse($conAltra->contains($type->getKey()));
    }

    public function test_the_institute_page_shows_the_documents_section(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $banca = Cliente::query()->create(['name' => 'Banca Vista']);

        Livewire::test(ViewIstituto::class, ['record' => $banca->getKey()])->assertSee('Documenti richiesti');
    }
}
