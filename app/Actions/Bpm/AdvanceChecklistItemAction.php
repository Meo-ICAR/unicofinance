<?php

namespace App\Actions\Bpm;

use App\Contracts\BpmAction;
use App\Contracts\BpmActionInterface;
use App\Contracts\BusinessRule;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * AdvanceChecklistItemAction
 *
 * Domain action that drives the "mark item checked" lifecycle for a
 * single TaskExecutionChecklistItem:
 *
 *   1. Load the runtime item and its template master (ChecklistItem).
 *   2. Evaluate skip_condition_class → if skip resolves true, abort (item not applicable).
 *   3. Evaluate require_condition_class → if true, override is_mandatory flag.
 *   4. If already checked, return idempotent success.
 *   5. Execute action_class (BpmAction / BpmActionInterface) inside a transaction.
 *   6. Mark is_checked = true and stamp checked_at.
 *
 * This action is the single authoritative source for checklist item
 * advancement, callable from:
 *  - Filament UI (BpmTaskRunnerController)
 *  - Cross-app REST API (BpmChecklistItemController)
 *  - Queued jobs
 *
 * @return array{
 *   success: bool,
 *   skipped: bool,
 *   is_mandatory: bool,
 *   message: string,
 *   action_class: string|null,
 *   action_executed: bool,
 * }
 */
class AdvanceChecklistItemAction
{
    /**
     * @param  TaskExecutionChecklistItem  $runtimeItem  The runtime item to advance.
     * @param  array<string,mixed>         $extraParams  Merged into action_params_snapshot.
     *
     * @throws RuntimeException  If action_class throws a validation error.
     */
    public function execute(
        TaskExecutionChecklistItem $runtimeItem,
        array $extraParams = [],
    ): array {
        $runtimeItem->load(['originalChecklistItem', 'taskExecution.client']);

        $masterItem = $runtimeItem->originalChecklistItem;
        $execution  = $runtimeItem->taskExecution;

        if (! $masterItem || ! $execution) {
            throw new RuntimeException('Could not load master ChecklistItem or parent TaskExecution.');
        }

        $client = $execution->client;

        // ── 1. Skip condition ──────────────────────────────────────────────────
        $skipClass = $masterItem->skip_condition_class;

        if ($skipClass && class_exists($skipClass)) {
            $rule = app($skipClass);

            if ($rule instanceof BusinessRule && $client && $rule->evaluate($client, $execution)) {
                Log::debug("BPM checklist item skipped by skip_condition_class.", [
                    'runtime_item_id'  => $runtimeItem->id,
                    'skip_class'       => $skipClass,
                    'execution_id'     => $execution->id,
                ]);

                return [
                    'success'         => true,
                    'skipped'         => true,
                    'is_mandatory'    => false,
                    'message'         => "Item skipped: {$skipClass} evaluated to true.",
                    'action_class'    => null,
                    'action_executed' => false,
                ];
            }
        }

        // ── 2. Require condition ───────────────────────────────────────────────
        $isMandatory   = (bool) $masterItem->is_mandatory;
        $requireClass  = $masterItem->require_condition_class;

        if ($requireClass && class_exists($requireClass)) {
            $rule = app($requireClass);

            if ($rule instanceof BusinessRule && $client && $rule->evaluate($client, $execution)) {
                $isMandatory = true;
            }
        }

        // ── 3. Idempotency ─────────────────────────────────────────────────────
        if ($runtimeItem->is_checked) {
            return [
                'success'         => true,
                'skipped'         => false,
                'is_mandatory'    => $isMandatory,
                'message'         => 'Item already checked.',
                'action_class'    => $masterItem->action_class,
                'action_executed' => false,
            ];
        }

        // ── 4. Execute action_class ────────────────────────────────────────────
        $actionClass    = $masterItem->action_class;
        $actionExecuted = false;

        // Merge stored params with any extra params passed by the caller
        $storedParams = is_array($runtimeItem->action_params_snapshot)
            ? $runtimeItem->action_params_snapshot
            : (json_decode($runtimeItem->action_params_snapshot ?? '{}', true) ?? []);

        $params = array_merge($storedParams, $extraParams);

        if ($actionClass && class_exists($actionClass)) {
            $action = app($actionClass);

            if (! ($action instanceof BpmAction || $action instanceof BpmActionInterface)) {
                throw new RuntimeException(
                    "Action class [{$actionClass}] must implement BpmAction or BpmActionInterface."
                );
            }

            // Execute inside a transaction — RuntimeException bubbles up and rolls back
            DB::transaction(function () use ($action, $execution, $params, $runtimeItem, &$actionExecuted) {
                $action->execute($execution, $params);

                // Mark as checked atomically with the action
                $runtimeItem->update([
                    'is_checked' => true,
                    'checked_at' => now(),
                ]);

                $actionExecuted = true;
            });

            Log::info('BPM checklist item advanced with action.', [
                'runtime_item_id' => $runtimeItem->id,
                'action_class'    => $actionClass,
                'execution_id'    => $execution->id,
            ]);
        } else {
            // No action — just mark as checked
            DB::transaction(function () use ($runtimeItem) {
                $runtimeItem->update([
                    'is_checked' => true,
                    'checked_at' => now(),
                ]);
            });
        }

        return [
            'success'         => true,
            'skipped'         => false,
            'is_mandatory'    => $isMandatory,
            'message'         => $actionExecuted
                ? "Item checked. Action [{$actionClass}] executed."
                : 'Item checked (no action configured).',
            'action_class'    => $actionClass,
            'action_executed' => $actionExecuted,
        ];
    }

    /**
     * Evaluate skip/require conditions WITHOUT marking the item.
     * Useful for pre-flight checks from the calling app.
     *
     * @return array{
     *   is_applicable: bool,
     *   is_mandatory: bool,
     *   skip_reason: string|null,
     *   require_overridden: bool,
     *   has_action: bool,
     *   action_class: string|null,
     * }
     */
    public function evaluate(TaskExecutionChecklistItem $runtimeItem): array
    {
        $runtimeItem->load(['originalChecklistItem', 'taskExecution.client']);

        $masterItem = $runtimeItem->originalChecklistItem;
        $execution  = $runtimeItem->taskExecution;
        $client     = $execution?->client;

        if (! $masterItem) {
            return [
                'is_applicable'      => true,
                'is_mandatory'       => false,
                'skip_reason'        => null,
                'require_overridden' => false,
                'has_action'         => false,
                'action_class'       => null,
            ];
        }

        // Skip evaluation
        $skipClass  = $masterItem->skip_condition_class;
        $skipReason = null;

        if ($skipClass && class_exists($skipClass)) {
            $rule = app($skipClass);
            if ($rule instanceof BusinessRule && $client && $rule->evaluate($client, $execution)) {
                $skipReason = $skipClass;
            }
        }

        // Require evaluation
        $isMandatory       = (bool) $masterItem->is_mandatory;
        $requireOverridden = false;
        $requireClass      = $masterItem->require_condition_class;

        if ($requireClass && class_exists($requireClass)) {
            $rule = app($requireClass);
            if ($rule instanceof BusinessRule && $client && $rule->evaluate($client, $execution)) {
                $isMandatory       = true;
                $requireOverridden = true;
            }
        }

        return [
            'is_applicable'      => $skipReason === null,
            'is_mandatory'       => $isMandatory,
            'skip_reason'        => $skipReason,
            'require_overridden' => $requireOverridden,
            'has_action'         => filled($masterItem->action_class),
            'action_class'       => $masterItem->action_class,
        ];
    }
}
