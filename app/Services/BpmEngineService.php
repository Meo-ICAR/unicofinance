<?php

namespace App\Services;

use App\Contracts\BusinessRule;
use App\Models\ChecklistItem;
use App\Models\TaskExecution;
use Illuminate\Support\Collection;

class BpmEngineService
{
    public function getAvailableActions(TaskExecution $execution): Collection
    {
        return $this->getOptionsForFilament('actions', $execution->client->company_id);
    }

    public function getAvailableConditions(TaskExecution $execution): Collection
    {
        return $this->getOptionsForFilament('conditions', $execution->client->company_id);
    }

    /**
     * Valuta quali checklist item sono applicabili e obbligatori per una specifica esecuzione.
     */
    public function getEvaluatedChecklist(TaskExecution $execution): Collection
    {
        // Recupera gli item legati al task del processo
        $items = $execution
            ->processTask
            ->checklists()
            ->with('items')
            ->get()
            ->pluck('items')
            ->flatten();

        return $items->map(function (ChecklistItem $item) use ($execution) {
            $client = $execution->client;

            // 1. Valuta la Skip Condition (se restituise true, l'item viene annullato)
            if ($item->skip_condition_class && class_exists($item->skip_condition_class)) {
                $skipRule = app($item->skip_condition_class);
                if ($skipRule instanceof BusinessRule && $skipRule->evaluate($client, $execution)) {
                    return null;
                }
            }

            // 2. Valuta la Require Condition (sovrascrive is_mandatory se la regola è true)
            $isMandatory = (bool) $item->is_mandatory;
            if ($item->require_condition_class && class_exists($item->require_condition_class)) {
                $requireRule = app($item->require_condition_class);
                if ($requireRule instanceof BusinessRule && $requireRule->evaluate($client, $execution)) {
                    $isMandatory = true;
                }
            }

            // Restituisce un oggetto arricchito per la UI
            return (object) [
                'id' => $item->id,
                'instruction' => $item->instruction,
                'is_mandatory' => $isMandatory,
                'original_item' => $item
            ];
        })->filter();  // Rimuove gli elementi nulli (quelli "skippati")
    }

    public function completeChecklistItem($executionId, $itemId)
    {
        $item = ChecklistItem::find($itemId);

        // ... logica di salvataggio del check ...

        // Se esiste una action_class, la eseguiamo
        if ($item->action_class && class_exists($item->action_class)) {
            $action = app($item->action_class);
            $action->execute(TaskExecution::find($executionId));
        }
    }
}
