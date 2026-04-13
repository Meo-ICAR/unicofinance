<?php

use App\Actions\Bpm\PromoteClientStatus;
use App\Contracts\BpmAction;
use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use App\Services\BpmActionRunner;

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

    $this->runner = app(BpmActionRunner::class);
});

it('executes action successfully when action class is valid', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Promote client status',
        'action_class' => PromoteClientStatus::class,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    $result = $this->runner->run($runtimeItem);

    expect($result['success'])->toBeTrue();
    expect($result['action_class'])->toBe(PromoteClientStatus::class);

    $this->client->refresh();
    expect($this->client->status)->toBe('approvato');
});

it('returns success when no action class is configured', function () {
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

    $result = $this->runner->run($runtimeItem);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toContain('No action_class configured');
});

it('returns failure when action class does not exist', function () {
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

    $result = $this->runner->run($runtimeItem);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('not found');
});

it('returns failure when class does not implement BpmAction', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    // Use a class that exists but doesn't implement BpmAction
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

    $result = $this->runner->run($runtimeItem);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('does not implement BpmAction');
});

it('validates action class without executing it', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Promote client status',
        'action_class' => PromoteClientStatus::class,
    ]);

    $runtimeItem = TaskExecutionChecklistItem::create([
        'task_execution_id' => $this->execution->id,
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    $result = $this->runner->validate($runtimeItem);

    expect($result['valid'])->toBeTrue();

    // Ensure action was NOT executed
    $this->client->refresh();
    expect($this->client->status)->toBe('in_trattativa');
});

it('validates when no action class is configured', function () {
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

    $result = $this->runner->validate($runtimeItem);

    expect($result['valid'])->toBeTrue();
});

it('validates and returns failure for non-existent class', function () {
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

    $result = $this->runner->validate($runtimeItem);

    expect($result['valid'])->toBeFalse();
    expect($result['message'])->toContain('not found');
});

it('validates and returns failure for class not implementing BpmAction', function () {
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

    $result = $this->runner->validate($runtimeItem);

    expect($result['valid'])->toBeFalse();
    expect($result['message'])->toContain('does not implement BpmAction');
});

it('resolves action from service container', function () {
    $resolved = app(BpmActionRunner::class);
    expect($resolved)->toBeInstanceOf(BpmActionRunner::class);
});
