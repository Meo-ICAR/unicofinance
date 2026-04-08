<?php

namespace App\Observers;

use App\Models\RaciAssignment;

class RaciAssignmentObserver
{
    public function saved(RaciAssignment $raciAssignment)
    {
        // Interveniamo solo se il ruolo assegnato è 'A' (Accountable)
        // o 'R' (Responsible) a seconda della tua business logic
        if ($raciAssignment->role !== 'A') {
            return;
        }

        $this->syncTaskAndProcess($raciAssignment);
    }

    protected function syncTaskAndProcess(RaciAssignment $raciAssignment)
    {
        $task = $raciAssignment->processTask;
        $newFunctionId = $raciAssignment->business_function_id;
        $oldFunctionId = $raciAssignment->getOriginal('business_function_id');

        // 1. Aggiornamento del TASK
        // Se il task non ha una funzione o se aveva quella precedente
        if (is_null($task->business_function_id) || $task->business_function_id == $oldFunctionId) {
            $task->business_function_id = $newFunctionId;
            $task->save();
        }

        // 2. Aggiornamento del PROCESSO
        $process = $task->process;
        if ($process) {
            // Aggiorniamo l'owner del processo se vuoto o se coincideva col vecchio
            if (is_null($process->business_function_id) || $process->business_function_id == $oldFunctionId) {
                $process->business_function_id = $newFunctionId;
                $process->save();
            }
        }
    }
}
