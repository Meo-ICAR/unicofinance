<?php

namespace App\Http\Controllers\Api;

use App\Actions\Bpm\CreateProcessExecutionAction;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * BpmProcessExecutionController
 *
 * REST API for external Laravel 13 applications (on a separate database,
 * same server) to trigger BPM process execution inside UnicoFinance.
 *
 * Authentication:  Authorization: Bearer <api_token>
 * Tenant scope:    derived from the token's company_id
 *
 * POST /api/bpm/executions
 *
 * Payload:
 * {
 *   "process_id":       1,          // required — the Process template to run
 *   "target_id":        42,         // required — the ID of the target entity
 *   "employee_id":      7,          // optional — the employee assignee
 *   "client_id":        3,          // optional — the client assignee
 *   "idempotency_key":  "uuid-...", // optional — prevents duplicate executions
 * }
 *
 * Note: target_type is NOT required from the caller — it is derived from
 * Process::target_model, enforcing the architectural invariant that the
 * template owns the target type definition.
 */
class BpmProcessExecutionController extends Controller
{
    public function __construct(
        protected CreateProcessExecutionAction $createExecution,
    ) {}

    /**
     * POST /api/bpm/executions
     *
     * Creates a TaskExecution (and its TaskExecutionChecklistItems) for each
     * ProcessTask belonging to the specified Process.
     */
    public function store(Request $request): JsonResponse
    {
        // ── 1. Validate input ──────────────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'process_id'       => ['required', 'integer', 'min:1'],
            'target_id'        => ['required', 'integer', 'min:1'],
            'employee_id'      => ['nullable', 'integer', 'min:1'],
            'client_id'        => ['nullable', 'integer', 'min:1'],
            'idempotency_key'  => ['nullable', 'string', 'max:128'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        // ── 2. Resolve authenticated company (set by middleware) ───────────────
        /** @var Company $company */
        $company = $request->attributes->get('api_company');

        // ── 3. Run the domain action ───────────────────────────────────────────
        try {
            $executions = $this->createExecution->execute(
                $validator->validated(),
                $company,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error'   => 'Bad request',
                'message' => $e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            // Idempotency collision
            return response()->json([
                'error'   => 'Conflict',
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'error'   => 'Internal server error',
                'message' => 'An unexpected error occurred. Check server logs.',
            ], 500);
        }

        // ── 4. Build response ──────────────────────────────────────────────────
        return response()->json([
            'success'    => true,
            'message'    => count($executions) . ' task execution(s) created.',
            'executions' => array_map(function ($execution) {
                return [
                    'id'                        => $execution->id,
                    'process_task_id'           => $execution->process_task_id,
                    'target_type'               => $execution->target_type,
                    'target_id'                 => $execution->target_id,
                    'employee_id'               => $execution->employee_id,
                    'client_id'                 => $execution->client_id,
                    'status'                    => $execution->status,
                    'idempotency_key'           => $execution->idempotency_key,
                    'checklist_items_count'     => $execution->executionItems()->count(),
                    'created_at'                => $execution->created_at->toISOString(),
                ];
            }, $executions),
        ], 201);
    }

    /**
     * GET /api/bpm/executions/{taskExecution}
     *
     * Returns the status and checklist items of a single execution.
     * Useful for the calling app to poll completion.
     */
    public function show(Request $request, int $executionId): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api_company');

        // Scope to company via processTask → process → company_id
        $execution = \App\Models\TaskExecution::whereHas('processTask.process', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->find($executionId);

        if (! $execution) {
            return response()->json([
                'error'   => 'Not found',
                'message' => "TaskExecution [{$executionId}] not found for this company.",
            ], 404);
        }

        $execution->load('executionItems.originalChecklistItem');

        return response()->json([
            'id'             => $execution->id,
            'status'         => $execution->status,
            'target_type'    => $execution->target_type,
            'target_id'      => $execution->target_id,
            'employee_id'    => $execution->employee_id,
            'client_id'      => $execution->client_id,
            'started_at'     => $execution->started_at?->toISOString(),
            'completed_at'   => $execution->completed_at?->toISOString(),
            'checklist_items' => $execution->executionItems->map(fn ($item) => [
                'id'                    => $item->id,
                'checklist_item_id'     => $item->checklist_item_id,
                'instruction_snapshot'  => $item->instruction_snapshot
                    ?? $item->originalChecklistItem?->instruction,
                'is_checked'            => (bool) $item->is_checked,
                'checked_at'            => $item->checked_at?->toISOString(),
            ]),
        ]);
    }
}
