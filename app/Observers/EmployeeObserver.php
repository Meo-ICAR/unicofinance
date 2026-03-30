<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\Process;
use App\Models\TaskExecution;

class EmployeeObserver
{
    public function created(Employee $employee)
    {
        // 1. Trova il processo di Onboarding Privacy/HR
        $process = Process::where('code', 'PROC-ONBOARDING')->first();

        if ($process) {
            foreach ($process->tasks as $task) {
                TaskExecution::create([
                    'process_task_id' => $task->id,
                    'employee_id' => $employee->id,  // Il neo-assunto è l'esecutore
                    'status' => 'todo',
                    'due_date' => now()->addDays(7),
                    'reference_number' => "ONB-{$employee->id}-" . now()->format('Ymd'),
                ]);
            }
        }
    }
}
