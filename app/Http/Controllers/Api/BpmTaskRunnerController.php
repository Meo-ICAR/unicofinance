<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use App\Services\BpmActionRunner;
use App\Services\BpmEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * BpmTaskRunnerController
 *
 * REST API endpoints for the BPM Task Runner UI.
 * Handles async checkbox toggling with action_class execution.
 */
class BpmTaskRunnerController extends Controller
{
    public function __construct(
        protected BpmEngineService $bpmEngine,
        protected BpmActionRunner $actionRunner,
    ) {
    }

    /**
     * GET /api/bpm/task-runner/{taskExecution}
     *
     * Returns the full payload needed to render the Task Runner UI:
     *  - TaskExecution with process task details
     *  - Checklists with items (enriched with evaluated conditions)
     *  - Runtime execution items (checked state)
     *  - Target model data (e.g. Proforma)
     */
    public function show(TaskExecution $taskExecution): JsonResponse
    {
        $taskExecution->load([
            'processTask.checklists.items' => function ($query) {
                $query->orderBy('sort_order');
            },
            'processTask.process',
            'executionItems.originalChecklistItem',
            'employee',
            'client',
        ]);

        // Load the polymorphic target
        if ($taskExecution->target_type && $taskExecution->target_id) {
            $taskExecution->load('target');
        }

        $evaluatedChecklist = $this->bpmEngine->getEvaluatedChecklist($taskExecution);

        return response()->json([
            'execution' => [
                'id' => $taskExecution->id,
                'status' => $taskExecution->status,
                'started_at' => $taskExecution->started_at?->toISOString(),
                'completed_at' => $taskExecution->completed_at?->toISOString(),
                'due_date' => $taskExecution->due_date?->toDateString(),
            ],
            'process_task' => [
                'id' => $taskExecution->processTask->id,
                'name' => $taskExecution->processTask->name,
                'description' => $taskExecution->processTask->description,
                'sequence_number' => $taskExecution->processTask->sequence_number,
                'process_name' => $taskExecution->processTask->process->name ?? null,
            ],
            'target' => $this->serializeTarget($taskExecution->target),
            'checklists' => $evaluatedChecklist->map(function ($item) use ($taskExecution) {
                $runtimeItem = $taskExecution->executionItems
                    ->firstWhere('checklist_item_id', $item->id);

                return [
                    'id' => $item->id,
                    'instruction' => $item->instruction,
                    'is_mandatory' => $item->is_mandatory,
                    'is_checked' => (bool) optional($runtimeItem)->is_checked,
                    'checked_at' => optional(optional($runtimeItem)->checked_at)->toISOString(),
                    'has_action' => (bool) $item->has_action,
                    'action_class' => $item->action_class,
                    'action_label' => $item->has_action
                        ? class_basename($item->action_class)
                        : null,
                ];
            })->values(),
            'assignee' => $taskExecution->employee ? [
                'id' => $taskExecution->employee->id,
                'name' => $taskExecution->employee->name,
                'email' => $taskExecution->employee->email,
            ] : null,
        ]);
    }

    /**
     * POST /api/bpm/task-runner/{taskExecution}/checklist/{checklistItem}/toggle
     *
     * Toggle a checklist item's checked state. If checking, evaluates skip/require
     * conditions and executes the action_class (if configured).
     *
     * Returns: { success, message, is_checked, checked_at, action_result }
     */
    public function toggleChecklistItem(
        TaskExecution $taskExecution,
        int $checklistItemId,
        Request $request,
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'is_checked' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $targetState = (bool) $request->input('is_checked');

        // Find the runtime item
        $runtimeItem = $taskExecution->executionItems
            ->firstWhere('checklist_item_id', $checklistItemId);

        if (!$runtimeItem) {
            return response()->json([
                'success' => false,
                'message' => "Checklist item #{$checklistItemId} not found in this execution.",
            ], 404);
        }

        // If already in the desired state, return early
        if ($runtimeItem->is_checked === $targetState) {
            return response()->json([
                'success' => true,
                'message' => $targetState ? 'Item is already checked.' : 'Item is already unchecked.',
                'is_checked' => $runtimeItem->is_checked,
                'checked_at' => optional($runtimeItem->checked_at)->toISOString(),
            ]);
        }

        try {
            if ($targetState) {
                // Checking the item — execute action_class if configured
                DB::transaction(function () use ($runtimeItem) {
                    $runtimeItem->update(['is_checked' => true]);
                    // Observer will fire the action_class automatically
                });

                $masterItem = $runtimeItem->originalChecklistItem;

                return response()->json([
                    'success' => true,
                    'message' => filled($masterItem?->action_class)
                        ? "Item checked. Action {$masterItem->action_class} executed."
                        : 'Item checked.',
                    'is_checked' => true,
                    'checked_at' => optional($runtimeItem->checked_at)->toISOString(),
                    'action_executed' => filled($masterItem?->action_class),
                    'action_class' => $masterItem?->action_class,
                ]);
            } else {
                // Unchecking — just revert the flag (no action re-run)
                $runtimeItem->update([
                    'is_checked' => false,
                    'checked_at' => null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Item unchecked.',
                    'is_checked' => false,
                    'checked_at' => null,
                    'action_executed' => false,
                ]);
            }
        } catch (RuntimeException $e) {
            // Action class validation failed (e.g. commissions don't match)
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'is_checked' => $runtimeItem->is_checked,
                'checked_at' => optional($runtimeItem->checked_at)->toISOString(),
            ], 422);
        }
    }

    /**
     * POST /api/bpm/task-runner/{taskExecution}/start
     *
     * Mark the TaskExecution as started.
     */
    public function start(TaskExecution $taskExecution): JsonResponse
    {
        if ($taskExecution->started_at) {
            return response()->json([
                'success' => true,
                'message' => 'Task already started.',
                'started_at' => $taskExecution->started_at->toISOString(),
            ]);
        }

        $taskExecution->update(['started_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Task started.',
            'started_at' => $taskExecution->started_at->toISOString(),
        ]);
    }

    /**
     * POST /api/bpm/task-runner/{taskExecution}/complete
     *
     * Mark the TaskExecution as completed (all mandatory items must be checked).
     */
    public function complete(TaskExecution $taskExecution): JsonResponse
    {
        // Verify all mandatory items are checked
        $evaluatedChecklist = $this->bpmEngine->getEvaluatedChecklist($taskExecution);

        $uncheckedMandatory = $evaluatedChecklist->filter(function ($item) use ($taskExecution) {
            $runtimeItem = $taskExecution->executionItems
                ->firstWhere('checklist_item_id', $item->id);

            return $item->is_mandatory && !optional($runtimeItem)->is_checked;
        });

        if ($uncheckedMandatory->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot complete task. The following mandatory items are not checked: '
                    . $uncheckedMandatory->pluck('instruction')->implode(', '),
            ], 422);
        }

        $taskExecution->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Mark any active deadline as completed
        $taskExecution->taskDeadline()->active()->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task completed successfully.',
            'completed_at' => $taskExecution->completed_at->toISOString(),
        ]);
    }

    /* ─── Private helpers ─── */

    /**
     * Serialize the polymorphic target for JSON responses.
     */
    protected function serializeTarget($target): ?array
    {
        if (!$target) {
            return null;
        }

        $data = $target->toArray();

        // Include commissions summary for Proforma
        if (method_exists($target, 'commissions')) {
            $data['total_commissions'] = $target->total_commissions;
            $data['commissions_count'] = $target->commissions()->count();
        }

        return [
            'type' => get_class($target),
            'id' => $target->id,
            'data' => $data,
        ];
    }
}
