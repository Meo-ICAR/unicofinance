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
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Task;
use Unico\Core\Models\TipoProdotto;

class DocumentiFascicoloTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @return array{0: Pratica, 1: array<string, Document>} */
    private function pratica(): array
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $prodotto = TipoProdotto::query()->create(['name' => 'Prodotto fascicolo', 'tipo_prodotto' => 'Prodotto fascicolo']);
        $task = Task::query()->create(['name' => 'Check list fascicolo', 'taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Prodotto fascicolo', 'tipoprodotto_id' => $prodotto->getKey(), 'is_active' => true]);
        $pratica = Pratica::query()->create(['codice_pratica' => 'FSC-1', 'tipoprodotto_id' => $prodotto->getKey(), 'upload_at' => now()]);
        $documenti = [];

        foreach (['verificato' => [true, false], 'caricato' => [true, false], 'dafirmare' => [true, true], 'anomalo' => [true, false], 'mancante' => [true, false], 'facoltativo' => [false, false]] as $nome => [$obbligatorio, $firma]) {
            $tipo = DocumentType::query()->create(['name' => 'Doc '.$nome, 'code' => 'FSC-'.$nome, 'is_signed' => $firma]);
            DocumentRequirement::query()->create(['task_id' => $task->getKey(), 'document_type_id' => $tipo->getKey(), 'is_required' => $obbligatorio, 'sort_order' => 1]);
            $stato = match ($nome) {
                'verificato' => DocumentStatus::APPROVED,
                'caricato', 'dafirmare' => DocumentStatus::UPLOADED,
                'anomalo' => DocumentStatus::ANOMALY,
                default => null,
            };

            if ($stato !== null) {
                $documenti[$nome] = Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => 'Doc '.$nome, 'status' => $stato]);
            }
        }

        return [Pratica::find($pratica->getKey()), $documenti];
    }

    public function test_the_header_shows_how_far_the_documents_of_the_current_task_are(): void
    {
        [$pratica] = $this->pratica();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertSee('Check list')
            ->assertSee('3/5 documenti obbligatori presenti')
            ->assertSee('1 da firmare')
            ->assertSee('1 da correggere')
            ->assertSee('mancano: Doc anomalo, Doc mancante');
    }

    public function test_the_table_uses_icon_columns_and_the_new_filter_lists_the_documents_to_complete(): void
    {
        [$pratica, $documenti] = $this->pratica();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords(array_values($documenti))
            ->assertTableColumnExists('fascicolo')
            ->assertTableColumnExists('controlli')
            ->assertTableColumnExists('scadenza')
            ->assertTableColumnStateSet('fascicolo', 'ok', $documenti['verificato'])
            ->assertTableColumnStateSet('fascicolo', 'caricato', $documenti['caricato'])
            ->assertTableColumnStateSet('fascicolo', 'da_firmare', $documenti['dafirmare'])
            ->assertTableColumnStateSet('fascicolo', 'problema', $documenti['anomalo'])
            ->filterTable('da_completare')
            ->assertCanSeeTableRecords([$documenti['anomalo']])
            ->assertCanNotSeeTableRecords([$documenti['verificato'], $documenti['caricato']]);
    }

    public function test_genera_plico_crea_i_documenti_mancanti_della_check_list_del_prodotto(): void
    {
        [$pratica, $documenti] = $this->pratica();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->callAction(TestAction::make('generaPlico')->table(), [])
            ->assertNotified('2 documenti richiesti creati');

        $this->assertSame(6, Document::query()->where('documentable_type', 'pratica')->where('documentable_id', (string) $pratica->getKey())->count());
        $this->assertSame(DocumentStatus::PENDING, Document::query()->where('name', 'Doc mancante')->firstOrFail()->status);
    }
}
