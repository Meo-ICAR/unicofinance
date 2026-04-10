<?php

namespace App\Services;

use App\Contracts\BusinessRule;
use App\Models\ChecklistItem;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
                'action_class' => $item->action_class,
                'has_action' => filled($item->action_class),
                'original_item' => $item,
            ];
        })->filter();  // Rimuove gli elementi nulli (quelli "skippati")
    }

    /**
     * Mark a runtime checklist item as checked and execute its action_class (if any).
     *
     * @param  int  $executionId   The TaskExecution ID
     * @param  int  $checklistItemId  The master ChecklistItem ID (from template)
     * @return array{success: bool, message: string, action_class: string|null}
     *
     * @throws RuntimeException  if the action_class validation fails
     */
    public function completeChecklistItem(int $executionId, int $checklistItemId): array
    {
        $execution = TaskExecution::with('executionItems.originalChecklistItem')
            ->findOrFail($executionId);

        // Find the runtime item that corresponds to this checklist item
        $runtimeItem = $execution->executionItems
            ->firstWhere('checklist_item_id', $checklistItemId);

        if (! $runtimeItem) {
            throw new RuntimeException(
                "Checklist item #{$checklistItemId} not found in execution #{$executionId}."
            );
        }

        if ($runtimeItem->is_checked) {
            return [
                'success' => true,
                'message' => 'Item already checked.',
                'action_class' => null,
            ];
        }

        $masterItem = $runtimeItem->originalChecklistItem;

        // Atomically mark as checked (and let the observer fire the action)
        DB::transaction(function () use ($runtimeItem) {
            $runtimeItem->update(['is_checked' => true]);
        });

        // The observer already executed the action_class inside its own transaction.
        // We just report the result.
        $actionClass = $masterItem?->action_class;

        return [
            'success' => true,
            'message' => filled($actionClass)
                ? "Item checked and action {$actionClass} executed."
                : 'Item checked (no action configured).',
            'action_class' => $actionClass,
        ];
    }

    /**
     * Uncheck a previously checked item (idempotent undo).
     * Does NOT re-run any action — only reverts the flag.
     */
    public function uncheckChecklistItem(int $executionId, int $checklistItemId): array
    {
        $execution = TaskExecution::findOrFail($executionId);

        $runtimeItem = $execution->executionItems
            ->firstWhere('checklist_item_id', $checklistItemId);

        if (! $runtimeItem) {
            throw new RuntimeException(
                "Checklist item #{$checklistItemId} not found in execution #{$executionId}."
            );
        }

        if (! $runtimeItem->is_checked) {
            return [
                'success' => true,
                'message' => 'Item is not checked.',
            ];
        }

        $runtimeItem->update([
            'is_checked' => false,
            'checked_at' => null,
        ]);

        return [
            'success' => true,
            'message' => 'Item unchecked.',
        ];
    }

    /* ─── Private helpers ─── */

    /**
     * Delega a BpmRegistryService per le opzioni filtrate per tenant.
     */
    private function getOptionsForFilament(string $type, string $companyId): Collection
    {
        return BpmRegistryService::getOptionsForFilament($type, $companyId);
    }
}
