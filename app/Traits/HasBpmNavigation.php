<?php

namespace App\Traits;

use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;
use App\Models\TaskExecution;
use Illuminate\Support\Str;

trait HasBpmNavigation
{
    /**
     * Determine which navigation resources the user can access
     * based on their RACI assignments through business functions.
     *
     * Navigation flow:
     *   User → Employee → BusinessFunction → RaciAssignment → ProcessTask → Process
     *
     * Returns an array of permission strings like "employee.lettura", "client.modifica", etc.
     */
    public function getBpmPermissions(): array
    {
        $employee = $this->employee;

        if (! $employee) {
            return [];
        }

        $permissions = [];

        // 1. Resources accessible via the employee's business functions and their RACI assignments
        $businessFunctionIds = $employee->businessFunctions()->pluck('business_functions.id');

        if ($businessFunctionIds->isNotEmpty()) {
            // Get all ProcessTasks where the employee's business functions have a RACI role
            $processTasks = ProcessTask::whereIn('id', function ($query) use ($businessFunctionIds) {
                $query->select('process_task_id')
                    ->from('raci_assignments')
                    ->whereIn('business_function_id', $businessFunctionIds);
            })->with('process')->get();

            foreach ($processTasks as $task) {
                $process = $task->process;

                if (! $process) {
                    continue;
                }

                $resource = $this->resolveResourceName($process);

                // Base read permission for any accessible task
                $permissions[] = "{$resource}.lettura";

                // Check if the user has an active TaskExecution on this task (can write/execute)
                $hasActiveExecution = $employee->taskExecutions()
                    ->where('process_task_id', $task->id)
                    ->whereIn('status', ['todo', 'in_progress'])
                    ->exists();

                if ($hasActiveExecution) {
                    $permissions[] = "{$resource}.modifica";
                    $permissions[] = "{$resource}.creazione";
                    $permissions[] = "{$resource}.esecuzione";
                }
            }
        }

        // 2. Resources accessible via subordinate employees (hierarchical access)
        if ($employee->supervisor_type !== 'no') {
            $subordinateTasks = TaskExecution::with('processTask.process')
                ->whereHas('employee', function ($query) use ($employee) {
                    $query->where('coordinated_by_id', $employee->id);
                })
                ->get();

            foreach ($subordinateTasks as $subTask) {
                $process = $subTask->processTask->process;

                if (! $process) {
                    continue;
                }

                $resource = $this->resolveResourceName($process);
                $permissions[] = "{$resource}.lettura";
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Resolve the resource name from a Process model.
     * Uses target_model if set, otherwise falls back to a slug of the process name.
     */
    protected function resolveResourceName(Process $process): string
    {
        if ($process->target_model) {
            return strtolower(class_basename($process->target_model));
        }

        return Str::slug($process->name, '_');
    }
}
