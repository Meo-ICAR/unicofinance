<?php

namespace App\Services;

use App\Contracts\BpmAction;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * BpmActionRunner — generic executor for BPM action_class side-effects.
 *
 * Responsibilities:
 *  1. Resolve the FQN string from a ChecklistItem's `action_class` column
 *  2. Validate the class exists and implements BpmAction
 *  3. Execute it inside a DB transaction tied to the TaskExecution's target
 *  4. Return structured results (success / failure + message)
 *
 * Usage:
 *     $runner = app(BpmActionRunner::class);
 *     $result = $runner->run($executionChecklistItem);
 */
class BpmActionRunner
{
    /**
     * Execute the action_class associated with a TaskExecutionChecklistItem.
     *
     * @return array{success: bool, message: string, action_class: string|null}
     *
     * @throws RuntimeException   when the action throws a validation error
     */
    public function run(TaskExecutionChecklistItem $executionItem): array
    {
        $masterItem = $executionItem->originalChecklistItem;

        if (!$masterItem || !$masterItem->action_class) {
            return [
                'success' => true,
                'message' => 'No action_class configured for this item.',
                'action_class' => null,
            ];
        }

        $actionClass = $masterItem->action_class;

        if (!class_exists($actionClass)) {
            Log::error("BPM Action class not found: {$actionClass}", [
                'checklist_item_id' => $masterItem->id,
            ]);

            return [
                'success' => false,
                'message' => "Action class not found: {$actionClass}",
                'action_class' => $actionClass,
            ];
        }

        $action = app($actionClass);

        if (!$action instanceof BpmAction) {
            Log::error("BPM Action class does not implement BpmAction: {$actionClass}");

            return [
                'success' => false,
                'message' => "Class {$actionClass} does not implement BpmAction.",
                'action_class' => $actionClass,
            ];
        }

        $execution = $executionItem->taskExecution;

        try {
            DB::transaction(function () use ($action, $execution) {
                $action->execute($execution);
            });

            Log::info("BPM Action executed successfully", [
                'action_class' => $actionClass,
                'execution_id' => $execution->id,
                'target_type' => $execution->target_type,
                'target_id' => $execution->target_id,
            ]);

            return [
                'success' => true,
                'message' => "Action {$actionClass} executed successfully.",
                'action_class' => $actionClass,
            ];
        } catch (Throwable $e) {
            Log::warning("BPM Action failed", [
                'action_class' => $actionClass,
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw so the caller (controller / observer) can reject the checkbox
            throw new RuntimeException(
                "BPM Action failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Dry-run: validate that the action_class can be resolved and would execute,
     * without actually running it. Useful for UI pre-flight checks.
     */
    public function validate(TaskExecutionChecklistItem $executionItem): array
    {
        $masterItem = $executionItem->originalChecklistItem;

        if (!$masterItem || !$masterItem->action_class) {
            return ['valid' => true, 'message' => 'No action to validate.'];
        }

        $class = $masterItem->action_class;

        if (!class_exists($class)) {
            return ['valid' => false, 'message' => "Class not found: {$class}"];
        }

        $action = app($class);

        if (!$action instanceof BpmAction) {
            return ['valid' => false, 'message' => "Does not implement BpmAction: {$class}"];
        }

        return ['valid' => true, 'message' => 'Action class is valid.'];
    }
}
