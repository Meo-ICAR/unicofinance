<?php

namespace App\Actions\Bpm;

use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * CreateProcessExecutionAction
 *
 * Domain action that creates a full TaskExecution + TaskExecutionChecklistItem
 * set for every ProcessTask belonging to a given Process.
 *
 * This follows the Template → Execution pattern described in ARCHITECTURE.md:
 * - resolves target_type from Process::target_model
 * - snapshots checklist instruction/action at execution time
 * - wraps everything in a single DB transaction
 * - enforces idempotency via idempotency_key
 * - respects tenant (company_id) scoping
 *
 * Callable from:
 *   - internal Filament actions (StartProcessAction)
 *   - the BpmProcessExecutionController (cross-app REST API)
 *   - queued jobs
 */
class CreateProcessExecutionAction
{
    /**
     * Execute the action.
     *
     * @param  array{
     *     process_id:   int,
     *     target_id:    int,
     *     employee_id?: int|null,
     *     client_id?:   int|null,
     *     status?:      string,
     *     idempotency_key?: string|null,
     * }  $payload
     * @param  Company  $company  The tenant that owns the process.
     *
     * @return TaskExecution[]  The first task execution created (head of chain).
     *
     * @throws InvalidArgumentException  If process not found / wrong tenant.
     * @throws RuntimeException          If idempotency_key already exists.
     */
    public function execute(array $payload, Company $company): array
    {
        // ── 1. Resolve Process ─────────────────────────────────────────────────
        /** @var Process|null $process */
        $process = Process::where('id', $payload['process_id'])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        if (! $process) {
            throw new InvalidArgumentException(
                "Process [{$payload['process_id']}] not found or inactive for company [{$company->id}]."
            );
        }

        // ── 2. Derive target_type from Process::target_model ───────────────────
        $targetType = $process->target_model;

        if (empty($targetType)) {
            throw new InvalidArgumentException(
                "Process [{$process->id}] has no target_model configured."
            );
        }

        // ── 3. Idempotency guard ───────────────────────────────────────────────
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            $existing = TaskExecution::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                throw new RuntimeException(
                    "An execution with idempotency_key [{$idempotencyKey}] already exists (id={$existing->id})."
                );
            }
        }

        // ── 4. Load ProcessTasks in order ──────────────────────────────────────
        $tasks = ProcessTask::where('process_id', $process->id)
            ->orderBy('sequence_number')
            ->get();

        if ($tasks->isEmpty()) {
            throw new InvalidArgumentException(
                "Process [{$process->id}] has no tasks defined."
            );
        }

        // ── 5. Create executions inside a transaction ──────────────────────────
        $created = [];

        DB::transaction(function () use (
            $tasks,
            $process,
            $targetType,
            $payload,
            $idempotencyKey,
            &$created,
        ) {
            $previousExecutionId = null;

            foreach ($tasks as $index => $task) {
                // Only the first task in the chain gets the idempotency_key
                $key = ($index === 0 && $idempotencyKey)
                    ? $idempotencyKey
                    : ($idempotencyKey ? "{$idempotencyKey}_task_{$task->id}" : null);

                /** @var TaskExecution $execution */
                $execution = TaskExecution::create([
                    'process_task_id'           => $task->id,
                    'process_version'           => $process->version ?? 1,
                    'employee_id'               => $payload['employee_id'] ?? null,
                    'client_id'                 => $payload['client_id'] ?? null,
                    'target_type'               => $targetType,
                    'target_id'                 => $payload['target_id'],
                    'status'                    => $payload['status'] ?? 'todo',
                    'previous_task_execution_id' => $previousExecutionId,
                    'idempotency_key'           => $key,
                    // snapshot of task name for auditability even if template changes
                    'snapshot'                  => json_encode([
                        'task_name'       => $task->name,
                        'task_sequence'   => $task->sequence_number,
                        'process_name'    => $process->name,
                        'process_version' => $process->version ?? 1,
                        'target_model'    => $targetType,
                    ]),
                ]);

                // ── 6. Clone checklist items as runtime snapshots ──────────────
                // Note: the TaskExecution::booted() observer also does this, but
                // here we enrich with instruction/action snapshots as per ARCHITECTURE.md.
                // The booted() observer is a safety net; this action provides the
                // authoritative snapshot.  We check for existing items to avoid
                // duplicates if the observer already fired.

                $checklistItems = ChecklistItem::whereHas('checklist', function ($q) use ($task) {
                    $q->where('process_task_id', $task->id);
                })->orderBy('sort_order')->get();

                foreach ($checklistItems as $item) {
                    // Guard: observer may have already created a bare item
                    $exists = TaskExecutionChecklistItem::where('task_execution_id', $execution->id)
                        ->where('checklist_item_id', $item->id)
                        ->exists();

                    if (! $exists) {
                        TaskExecutionChecklistItem::create([
                            'task_execution_id'        => $execution->id,
                            'checklist_item_id'        => $item->id,
                            'instruction_snapshot'     => $item->instruction,
                            'action_class_snapshot'    => $item->action_class,
                            'action_params_snapshot'   => $item->action_params,
                            'is_checked'               => false,
                        ]);
                    } else {
                        // Enrich the bare item created by the observer with snapshots
                        TaskExecutionChecklistItem::where('task_execution_id', $execution->id)
                            ->where('checklist_item_id', $item->id)
                            ->update([
                                'instruction_snapshot'   => $item->instruction,
                                'action_class_snapshot'  => $item->action_class,
                                'action_params_snapshot' => $item->action_params,
                            ]);
                    }
                }

                $created[]           = $execution;
                $previousExecutionId = $execution->id;
            }
        });

        return $created;
    }
}
