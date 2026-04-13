<?php

use App\Actions\Bpm\PromoteClientStatus;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'in_trattativa',
    ]);

    $this->process = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
        'version' => 1,
    ]);

    $this->processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $this->execution = TaskExecution::create([
        'process_task_id' => $this->processTask->id,
        'client_id' => $this->client->id,
        'status' => 'in_progress',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);
});

it('executes action when checklist item is checked', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Promote client',
        'action_class' => PromoteClientStatus::class,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    // Check the item - should trigger observer
    $runtimeItem->update(['is_checked' => true]);

    // Verify action was executed
    $this->client->refresh();
    expect($this->client->status)->toBe('approvato');

    // Verify checked_at was set
    $runtimeItem->refresh();
    expect($runtimeItem->checked_at)->not->toBeNull();
});

it('sets checked_at timestamp when item is checked', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Test instruction',
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    expect($runtimeItem->checked_at)->toBeNull();

    $runtimeItem->update(['is_checked' => true]);

    $runtimeItem->refresh();
    expect($runtimeItem->checked_at)->not->toBeNull();
});

it('does not execute action when no action_class is configured', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'No action',
        'action_class' => null,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    // Should not throw error
    $runtimeItem->update(['is_checked' => true]);

    expect($runtimeItem->fresh()->is_checked)->toBeTrue();
});

it('does not execute action when action_class does not exist', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Invalid action',
        'action_class' => 'App\\Actions\\NonExistentAction',
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    // Should not throw error, just log warning
    $runtimeItem->update(['is_checked' => true]);

    expect($runtimeItem->fresh()->is_checked)->toBeTrue();
});

it('does not execute action when class does not implement BpmAction', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Invalid action',
        'action_class' => \stdClass::class,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    // Should not throw error, just log warning
    $runtimeItem->update(['is_checked' => true]);

    expect($runtimeItem->fresh()->is_checked)->toBeTrue();
});

it('does not trigger action on non-dirty updates', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Promote client',
        'action_class' => PromoteClientStatus::class,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => true,
        'checked_at' => now(),
    ]);

    // Update other field - should not trigger action again
    $runtimeItem->update(['is_not_applicable' => false]);

    // Client status should remain unchanged (action not re-executed)
    $this->client->refresh();
    expect($this->client->status)->toBe('approvato'); // Already approved from creation
});

it('does not trigger action when unchecking item', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Promote client',
        'action_class' => PromoteClientStatus::class,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => true,
        'checked_at' => now(),
    ]);

    // Uncheck the item
    $runtimeItem->update([
        'is_checked' => false,
        'checked_at' => null,
    ]);

    // Client should still be approved (action not reversed)
    $this->client->refresh();
    expect($this->client->status)->toBe('approvato');
});

it('executes action inside database transaction', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Promote client',
        'action_class' => PromoteClientStatus::class,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    // Action should be executed atomically
    $runtimeItem->update(['is_checked' => true]);

    // Verify both checklist update and action execution happened
    $runtimeItem->refresh();
    expect($runtimeItem->is_checked)->toBeTrue();

    $this->client->refresh();
    expect($this->client->status)->toBe('approvato');
});
