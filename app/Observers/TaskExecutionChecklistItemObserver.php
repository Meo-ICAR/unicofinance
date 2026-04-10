<?php

namespace App\Observers;

use App\Contracts\BpmAction;
use App\Models\TaskExecutionChecklistItem;
use Illuminate\Support\Facades\Log;

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
     *
     * When is_checked transitions from false → true, we:
     *  1. Load the action_class from the master ChecklistItem
     *  2. Instantiate and execute it against the TaskExecution's target model
     */
    public function updated(TaskExecutionChecklistItem $item): void
    {
        // The actual DB column is `is_checked`, NOT `is_completed`
        if (! $item->is_dirty('is_checked') || ! $item->is_checked) {
            return;
        }

        // Set checked_at timestamp if not already set
        if (! $item->checked_at) {
            $item->checked_at = now();
            $item->saveQuietly();
        }

        $masterItem = $item->originalChecklistItem;

        if (! $masterItem || ! $masterItem->action_class) {
            return;
        }

        if (! class_exists($masterItem->action_class)) {
            Log::warning("BPM Action class not found: {$masterItem->action_class}", [
                'checklist_item_id' => $masterItem->id,
            ]);
            return;
        }

        $action = app($masterItem->action_class);

        if (! $action instanceof BpmAction) {
            Log::warning("BPM Action class does not implement BpmAction: {$masterItem->action_class}");
            return;
        }

        // Execute inside a transaction so side-effects are atomic
        \Illuminate\Support\Facades\DB::transaction(function () use ($action, $item) {
            $action->execute($item->taskExecution);
        });
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
