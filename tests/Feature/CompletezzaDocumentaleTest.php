<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\Pratica;
use App\Models\User;
use App\Services\Istruttoria\CompletezzaDocumentale;
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
use Unico\Core\Models\PraticaStato;
use Unico\Core\Models\Task;
use Unico\Core\Models\TipoProdotto;

class CompletezzaDocumentaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @return array{0: Pratica, 1: DocumentType, 2: DocumentType} */
    private function pratica(): array
    {
        $prodotto = TipoProdotto::query()->create(['name' => 'Prodotto test', 'tipo_prodotto' => 'Prodotto test']);
        $identita = DocumentType::query()->create(['name' => 'Identità test', 'code' => 'IT-T']);
        $reddito = DocumentType::query()->create(['name' => 'Reddito test', 'code' => 'RD-T']);
        $task = Task::query()->create(['name' => 'Check list test', 'taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_value' => 'Prodotto test', 'tipoprodotto_id' => $prodotto->getKey(), 'is_active' => true]);

        foreach ([$identita, $reddito] as $ordine => $tipo) {
            DocumentRequirement::query()->create(['task_id' => $task->getKey(), 'document_type_id' => $tipo->getKey(), 'is_required' => true, 'sort_order' => $ordine + 1]);
        }

        $pratica = Pratica::query()->create(['codice_pratica' => 'ZZCMP-1', 'tipoprodotto_id' => $prodotto->getKey(), 'upload_at' => now()]);

        return [$pratica, $identita, $reddito];
    }

    private function documento(Pratica $pratica, DocumentType $tipo, DocumentStatus $status): Document
    {
        return Document::query()->create(['documentable_type' => 'pratica', 'documentable_id' => (string) $pratica->getKey(), 'document_type_id' => $tipo->getKey(), 'name' => $tipo->name, 'status' => $status]);
    }

    public function test_the_required_documents_missing_are_those_not_uploaded_ok_or_na(): void
    {
        [$pratica, $identita, $reddito] = $this->pratica();
        $servizio = app(CompletezzaDocumentale::class);

        $this->assertSame(0, $servizio->per($pratica)['presenti']);
        $this->assertSame(2, $servizio->per($pratica)['totale']);

        $this->documento($pratica, $identita, DocumentStatus::UPLOADED);
        $this->documento($pratica, $reddito, DocumentStatus::ANOMALY);
        $this->assertSame(1, $servizio->per($pratica)['presenti']);

        Document::query()->where('document_type_id', $reddito->getKey())->update(['status' => DocumentStatus::NA->value]);
        $this->assertCount(0, $servizio->per($pratica)['mancanti']);
    }

    public function test_the_outcome_is_set_on_a_document_with_a_note_for_negative_outcomes(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($user = User::factory()->create(['role' => 'super_admin']));
        [$pratica, $identita] = $this->pratica();
        $documento = $this->documento($pratica, $identita, DocumentStatus::UPLOADED);

        $manager = DocumentsRelationManager::class;

        Livewire::test($manager, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->assertCanSeeTableRecords([$documento])
            ->mountAction(TestAction::make('edit')->table($documento))
            ->assertActionExists(TestAction::make('esitoIstruttoria'))
            ->callAction(TestAction::make('esitoIstruttoria'), ['status' => DocumentStatus::REJECTED->value])
            ->assertHasActionErrors(['nota' => 'required']);

        Livewire::test($manager, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class])
            ->callAction([TestAction::make('edit')->table($documento), TestAction::make('esitoIstruttoria')], ['status' => DocumentStatus::SUPPLEMENTARY->value, 'nota' => 'Richiesto dalla banca'])
            ->assertHasNoActionErrors();

        $documento->refresh();
        $this->assertSame(DocumentStatus::SUPPLEMENTARY, $documento->status);
        $this->assertSame('Richiesto dalla banca', $documento->rejection_note);
        $this->assertSame($user->id, (int) $documento->verified_by);
    }

    public function test_the_state_change_with_missing_documents_warns_but_does_not_block_by_default(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        [$pratica] = $this->pratica();
        config(['istruttoria.stati_controllati' => ['FASCICOLO TEST']]);
        $stato = PraticaStato::query()->create(['codice' => 'FASCICOLO TEST', 'tipo_finanziamento' => 'Prodotto test', 'name' => 'FASCICOLO TEST', 'ordine' => 0, 'is_working' => true, 'colore' => 'gray']);

        Livewire::test(ListPraticas::class)
            ->callTableAction('cambiaStato', $pratica, data: ['stato' => $stato->getKey(), 'motivazione' => 'Recupero storico'])
            ->assertNotified('Documentazione incompleta');

        $this->assertSame($stato->getKey(), $pratica->fresh()->pratica_stato_id);
    }

    public function test_the_state_change_is_refused_when_the_documentation_is_blocking(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        [$pratica] = $this->pratica();
        config(['istruttoria.stati_controllati' => ['FASCICOLO TEST'], 'istruttoria.documentazione_bloccante' => true]);
        $stato = PraticaStato::query()->create(['codice' => 'FASCICOLO TEST', 'tipo_finanziamento' => 'Prodotto test', 'name' => 'FASCICOLO TEST', 'ordine' => 0, 'is_working' => true, 'colore' => 'gray']);

        Livewire::test(ListPraticas::class)
            ->callTableAction('cambiaStato', $pratica, data: ['stato' => $stato->getKey(), 'motivazione' => 'Prova'])
            ->assertNotified('Documentazione incompleta');

        $this->assertNull($pratica->fresh()->pratica_stato_id);
    }
}
