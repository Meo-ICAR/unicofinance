<?php

namespace App\Observers;

use App\Models\TaskExecutionChecklistItem;

class TaskExecutionChecklistItemObserver
{
    /**
     * Handle the TaskExecutionChecklistItem "created" event.
     */
    public function created(TaskExecutionChecklistItem $taskExecutionChecklistItem): void
    {
        //
    }

    /**
     * Handle the TaskExecutionChecklistItem "updated" event.
     */
    public function updated(TaskExecutionChecklistItem $item): void
    {
        // Verifichiamo se il campo 'is_completed' è appena passato da false a true
        if ($item->is_dirty('is_completed') && $item->is_completed) {
            // Recuperiamo la configurazione dell'azione dal master (ChecklistItem)
            $masterItem = $item->checklistVisibilityConfig;  // La tua relazione verso ChecklistItem

            if ($masterItem && $masterItem->action_class && class_exists($masterItem->action_class)) {
                // Eseguiamo l'azione
                $action = app($masterItem->action_class);

                // Passiamo l'esecuzione del task all'azione
                // Assicurati di avere la relazione definita nel model
                $action->execute($item->taskExecution);
            }
        }
    }

    /**
     * Handle the TaskExecutionChecklistItem "deleted" event.
     */
    public function deleted(TaskExecutionChecklistItem $taskExecutionChecklistItem): void
    {
        //
    }

    /**
     * Handle the TaskExecutionChecklistItem "restored" event.
     */
    public function restored(TaskExecutionChecklistItem $taskExecutionChecklistItem): void
    {
        //
    }

    /**
     * Handle the TaskExecutionChecklistItem "force deleted" event.
     */
    public function forceDeleted(TaskExecutionChecklistItem $taskExecutionChecklistItem): void
    {
        //
    }
}
