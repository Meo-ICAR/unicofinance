<?php

namespace App\Services\Preventivazione;

use Illuminate\Support\Collection;
use Unico\Core\Models\DocumentRequirement;
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
