<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bpm\AdvanceChecklistItemAction;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

/**
 * BpmChecklistItemController
 *
 * Cross-app REST API for advancing TaskExecutionChecklistItem state.
 *
 * Authentication:  Authorization: Bearer <api_token>
 * Tenant scope:    derived from the token's company_id (via middleware)
 *
 * ── Endpoints ─────────────────────────────────────────────────────────────────
 *
 *  GET  /api/bpm/executions/{execution}/checklist
 *       → list all runtime items with their evaluated state
 *
 *  GET  /api/bpm/executions/{execution}/checklist/{item}/evaluate
 *       → evaluate skip/require conditions without mutating state
 *
 *  POST /api/bpm/executions/{execution}/checklist/{item}/check
 *       → advance the item: evaluate conditions → run action_class → mark checked
 *
 *  POST /api/bpm/executions/{execution}/checklist/{item}/uncheck
 *       → revert is_checked (no action re-run)
 */
class BpmChecklistItemController extends Controller
{
    public function __construct(
        protected AdvanceChecklistItemAction $advanceItem,
    ) {}

    // ── GET /api/bpm/executions/{execution}/checklist ─────────────────────────

    /**
     * List all runtime checklist items for an execution, including
     * the resolved evaluation of skip/require conditions.
     */
    public function index(Request $request, int $executionId): JsonResponse
    {
        $execution = $this->resolveExecution($executionId, $request->attributes->get('api_company'));

        if (! $execution) {
            return $this->notFound("TaskExecution [{$executionId}]");
        }

        $execution->load('executionItems.originalChecklistItem', 'client');

        $items = $execution->executionItems->map(function (TaskExecutionChecklistItem $item) {
            $evaluated = $this->advanceItem->evaluate($item);

            return [
                'id'                   => $item->id,
                'checklist_item_id'    => $item->checklist_item_id,
                'instruction'          => $item->instruction_snapshot
                    ?? $item->originalChecklistItem?->instruction,
                'action_class'         => $evaluated['action_class'],
                'has_action'           => $evaluated['has_action'],
                'is_applicable'        => $evaluated['is_applicable'],
                'is_mandatory'         => $evaluated['is_mandatory'],
                'require_overridden'   => $evaluated['require_overridden'],
                'skip_reason'          => $evaluated['skip_reason'],
                'is_checked'           => (bool) $item->is_checked,
                'checked_at'           => $item->checked_at?->toISOString(),
            ];
        });

        return response()->json([
            'execution_id' => $execution->id,
            'status'       => $execution->status,
            'items'        => $items->values(),
        ]);
    }

    // ── GET /api/bpm/executions/{execution}/checklist/{item}/evaluate ─────────

    /**
     * Evaluate skip/require conditions for a specific runtime item
     * WITHOUT mutating anything. Use this for pre-flight checks.
     */
    public function evaluate(Request $request, int $executionId, int $itemId): JsonResponse
    {
        $execution = $this->resolveExecution($executionId, $request->attributes->get('api_company'));

        if (! $execution) {
            return $this->notFound("TaskExecution [{$executionId}]");
        }

        $runtimeItem = $this->resolveItem($execution, $itemId);

        if (! $runtimeItem) {
            return $this->notFound("TaskExecutionChecklistItem [{$itemId}]");
        }

        $result = $this->advanceItem->evaluate($runtimeItem);

        return response()->json(array_merge($result, [
            'runtime_item_id' => $runtimeItem->id,
            'is_checked'      => (bool) $runtimeItem->is_checked,
            'checked_at'      => $runtimeItem->checked_at?->toISOString(),
        ]));
    }

    // ── POST /api/bpm/executions/{execution}/checklist/{item}/check ───────────

    /**
     * Advance the checklist item:
     *
     *   1. Evaluate skip_condition_class → abort if skipped
     *   2. Evaluate require_condition_class → may override is_mandatory
     *   3. Execute action_class (BpmAction) inside a DB transaction
     *   4. Mark is_checked = true, stamp checked_at
     *
     * Accepts optional extra params to merge into action_params_snapshot.
     */
    public function check(Request $request, int $executionId, int $itemId): JsonResponse
    {
        // Optional extra params for action_class
        $validator = Validator::make($request->all(), [
            'params'    => ['nullable', 'array'],
            'params.*'  => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $execution = $this->resolveExecution($executionId, $request->attributes->get('api_company'));

        if (! $execution) {
            return $this->notFound("TaskExecution [{$executionId}]");
        }

        $runtimeItem = $this->resolveItem($execution, $itemId);

        if (! $runtimeItem) {
            return $this->notFound("TaskExecutionChecklistItem [{$itemId}]");
        }

        try {
            $result = $this->advanceItem->execute(
                runtimeItem: $runtimeItem,
                extraParams: $request->input('params', []),
            );
        } catch (RuntimeException $e) {
            // action_class threw a validation error — do NOT mark as checked
            return response()->json([
                'success' => false,
                'error'   => 'Action failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error'   => 'Internal server error',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }

        // If item was skipped by condition, 200 is correct (no mutation happened)
        $status = $result['skipped'] ? 200 : 200;

        return response()->json(array_merge($result, [
            'runtime_item_id' => $runtimeItem->id,
            'is_checked'      => (bool) $runtimeItem->fresh()->is_checked,
            'checked_at'      => $runtimeItem->fresh()->checked_at?->toISOString(),
        ]), $status);
    }

    // ── POST /api/bpm/executions/{execution}/checklist/{item}/uncheck ─────────

    /**
     * Revert is_checked to false.
     * Does NOT re-run the action_class.
     */
    public function uncheck(Request $request, int $executionId, int $itemId): JsonResponse
    {
        $execution = $this->resolveExecution($executionId, $request->attributes->get('api_company'));

        if (! $execution) {
            return $this->notFound("TaskExecution [{$executionId}]");
        }

        $runtimeItem = $this->resolveItem($execution, $itemId);

        if (! $runtimeItem) {
            return $this->notFound("TaskExecutionChecklistItem [{$itemId}]");
        }

        if (! $runtimeItem->is_checked) {
            return response()->json([
                'success' => true,
                'message' => 'Item is already unchecked.',
                'is_checked' => false,
            ]);
        }

        $runtimeItem->update([
            'is_checked' => false,
            'checked_at' => null,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Item unchecked successfully.',
            'is_checked' => false,
            'checked_at' => null,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve a TaskExecution scoped to the current tenant (company).
     */
    private function resolveExecution(int $id, Company $company): ?TaskExecution
    {
        return TaskExecution::whereHas('processTask.process', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->with('executionItems')->find($id);
    }

    /**
     * Resolve a TaskExecutionChecklistItem belonging to a specific execution.
     * $itemId can be either the runtime item ID or the master checklist_item_id.
     */
    private function resolveItem(TaskExecution $execution, int $itemId): ?TaskExecutionChecklistItem
    {
        return $execution->executionItems
            ->first(fn ($i) => $i->id === $itemId || $i->checklist_item_id === $itemId);
    }

    private function notFound(string $label): JsonResponse
    {
        return response()->json([
            'error'   => 'Not found',
            'message' => "{$label} not found for this company.",
        ], 404);
    }
}
