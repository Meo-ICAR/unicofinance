<?php

namespace Tests\Feature;

use App\Models\Pratica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Unico\Core\Documents\PracticeTaskDocuments;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\Task;
use Unico\Core\Models\TipoProdotto;

class FascicoloRacesEreditaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [null, 'core'];

    /** @return array{0: Task, 1: Task, 2: Task} */
    private function task(): array
    {
        $cessione = TipoProdotto::query()->create(['name' => 'Cessione ZZ']);
        $delega = TipoProdotto::query()->create(['name' => 'Delega ZZ']);
        $fascicolo = DocumentType::query()->create(['name' => 'Fascicolo Races ZZ', 'code' => 'RACES-ZZ']);
        $allegato = DocumentType::query()->create(['name' => 'Allegato B ZZ', 'code' => 'ALLB-ZZ']);

        $base = Task::query()->create(['taskable' => 'pratica', 'name' => 'Fascicolo Races', 'is_active' => false]);
        $base->documentRequirements()->create(['document_type_id' => $fascicolo->getKey(), 'is_required' => true, 'phase' => 'invio_banca', 'sort_order' => 1]);

        $figli = [];
        foreach ([[$cessione, true], [$delega, false]] as [$prodotto, $conAllegato]) {
            $figlio = Task::query()->create([
                'taskable' => 'pratica', 'name' => $prodotto->name.' · FASCICOLO COMPLETO', 'is_active' => true, 'inherits_task_id' => $base->getKey(),
                'trigger_field' => 'tipo_prodotto', 'tipoprodotto_id' => $prodotto->getKey(), 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'FASCICOLO COMPLETO',
            ]);

            if ($conAllegato) {
                $figlio->documentRequirements()->create(['document_type_id' => $allegato->getKey(), 'is_required' => true, 'sort_order' => 2]);
            }

            $figli[] = $figlio;
        }

        return [$base, $figli[0], $figli[1]];
    }

    /** @return list<string> */
    private function nomi(string $prodotto): array
    {
        return app(PracticeTaskDocuments::class)->forProductStatus($prodotto, 'FASCICOLO COMPLETO')
            ->map(fn (DocumentRequirement $requisito): string => $requisito->documentType->name)->sort()->values()->all();
    }

    public function test_the_tasks_that_call_the_races_task_get_its_document_and_keep_their_own(): void
    {
        $this->task();

        $this->assertSame(['Allegato B ZZ', 'Fascicolo Races ZZ'], $this->nomi('Cessione ZZ'));
        $this->assertSame(['Fascicolo Races ZZ'], $this->nomi('Delega ZZ'));
    }

    public function test_changing_the_races_task_changes_every_task_that_calls_it(): void
    {
        [$base] = $this->task();
        $nuovo = DocumentType::query()->create(['name' => 'Fascicolo Races ZZ 2027', 'code' => 'RACES-ZZ-27']);

        $base->documentRequirements()->delete();
        $base->documentRequirements()->create(['document_type_id' => $nuovo->getKey(), 'is_required' => true, 'sort_order' => 1]);

        $this->assertSame(['Allegato B ZZ', 'Fascicolo Races ZZ 2027'], $this->nomi('Cessione ZZ'));
        $this->assertSame(['Fascicolo Races ZZ 2027'], $this->nomi('Delega ZZ'));
    }

    public function test_a_document_of_the_calling_task_replaces_the_same_document_of_the_races_task(): void
    {
        [$base, $cessione] = $this->task();
        $tipo = $base->documentRequirements()->firstOrFail()->documentType;

        $cessione->documentRequirements()->create(['document_type_id' => $tipo->getKey(), 'is_required' => false, 'sort_order' => 1]);

        $effettivi = $cessione->fresh()->requisitiEffettivi();
        $this->assertCount(2, $effettivi);
        $this->assertFalse((bool) $effettivi->firstWhere('document_type_id', $tipo->getKey())->is_required, 'Il requisito del figlio vince su quello del task base.');
    }

    public function test_the_inactive_races_task_is_not_a_plico_of_its_own(): void
    {
        $this->task();
        $pratica = Pratica::query()->create(['codice_pratica' => 'RACES-1', 'upload_at' => now(), 'clienti_id' => Cliente::query()->create(['name' => 'Banca ZZ'])->getKey()]);

        $this->assertNotContains('Fascicolo Races', Task::applicabiliA($pratica)->pluck('name')->all());
    }

    public function test_inheritance_chains_through_a_task_that_calls_the_races_task(): void
    {
        [, $cessione] = $this->task();
        $ristrutturazione = Task::query()->create(['taskable' => 'pratica', 'name' => 'Cessione ristrutturazione ZZ · FASCICOLO COMPLETO', 'is_active' => true, 'inherits_task_id' => $cessione->getKey()]);

        $nomi = $ristrutturazione->requisitiEffettivi()->map(fn (DocumentRequirement $requisito): string => $requisito->documentType->name)->sort()->values()->all();

        $this->assertSame(['Allegato B ZZ', 'Fascicolo Races ZZ'], $nomi, 'Eredita dal task figlio, che a sua volta eredita dal task base.');
    }
}
