<?php

namespace App\Services\Preventivazione;

use Illuminate\Support\Collection;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\DocumentRequirement;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\Task;

/** Documenti del task di pratica collegato al tipo prodotto e allo stato. */
class ProductTaskDocuments
{
    /**
     * @return Collection<int, DocumentRequirement>
     */
    public function forProductStatus(string $productName, string $statusCode): Collection
    {
        $task = $this->task($productName, $statusCode);

        // Il catalogo attuale chiama PERFEZIONATA il task dei documenti di perfezionamento.
        if ($task === null && $statusCode === 'PERFEZIONARE') {
            $task = $this->task($productName, 'PERFEZIONATA');
        }

        if ($task === null) {
            return collect();
        }

        return $task->documentRequirements()
            ->with('documentType')
            ->orderBy('sort_order')
            ->get();
    }

    /** Crea sulla pratica i documenti del task di perfezionamento del suo tipo prodotto. */
    public function attachToPractice(Pratica $pratica): int
    {
        $productName = $pratica->tipoprodotto?->name;

        if ($productName === null) {
            return 0;
        }

        $created = 0;

        foreach ($this->forProductStatus($productName, 'PERFEZIONARE') as $requirement) {
            $type = $requirement->documentType;

            if ($type === null) {
                continue;
            }

            $document = Document::query()->firstOrCreate(
                [
                    'documentable_type' => 'pratica',
                    'documentable_id' => (string) $pratica->getKey(),
                    'document_type_id' => $type->getKey(),
                ],
                [
                    'company_id' => is_numeric($pratica->company_id) ? (int) $pratica->company_id : null,
                    'name' => $type->name,
                    'is_template' => (bool) $type->is_template,
                    'is_monitored' => (bool) $type->is_monitored,
                    'doctype' => $type->doctype,
                    'status' => DocumentStatus::PENDING,
                ],
            );

            if ($document->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function task(string $productName, string $statusCode): ?Task
    {
        return Task::query()
            ->where('taskable', 'pratica')
            ->where('is_active', true)
            ->where('trigger_field', 'tipo_prodotto')
            ->where('trigger_value', $productName)
            ->where('trigger_subfield', 'stato_pratica')
            ->where('trigger_subvalue', $statusCode)
            ->first();
    }
}
