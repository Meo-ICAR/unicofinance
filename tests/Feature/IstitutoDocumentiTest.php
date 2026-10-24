<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Istituti\IstitutoResource;
use App\Filament\Resources\Istituti\Pages\EditIstituto;
use App\Filament\Resources\Istituti\RelationManagers\DocumentiIstitutoRelationManager;
use App\Filament\Resources\ListinoProvvigionales\ListinoProvvigionaleResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsCoreData;
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
    use SeedsCoreData;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCoreData('address_types', 'document_types', 'tasks', 'document_requirements', 'pdf_modules', 'pdf_module_fields', 'pratica_stati', 'tipoprodotto', 'tipoprodotto_sub');
    }

    public function test_a_bank_document_is_attached_to_the_product_tasks_and_only_asked_for_that_bank(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $banca = Cliente::query()->create(['name' => 'Banca Test']);
        $altra = Cliente::query()->create(['name' => 'Altra Banca']);

        Livewire::test(DocumentiIstitutoRelationManager::class, ['ownerRecord' => $banca, 'pageClass' => EditIstituto::class])
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

        Livewire::test(EditIstituto::class, ['record' => $banca->getKey()])->assertSee('Modulistica banca');
    }

    public function test_the_read_only_view_pages_are_gone_and_the_institute_edit_shows_its_registry(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $banca = Cliente::query()->create(['name' => 'Banca Anagrafica', 'abi' => '03069', 'piva' => '00000000001']);

        foreach ([ClientResource::class, ListinoProvvigionaleResource::class, IstitutoResource::class] as $resource) {
            $this->assertArrayNotHasKey('view', $resource::getPages());
        }

        Livewire::test(EditIstituto::class, ['record' => $banca->getKey()])
            ->assertFormSet(['name' => 'Banca Anagrafica', 'abi' => '03069', 'piva' => '00000000001']);
    }
}
