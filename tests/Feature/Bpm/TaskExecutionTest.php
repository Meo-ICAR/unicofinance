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
    ]);

    $this->process = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
        'version' => 1,
    ]);
});

it('creates task execution with correct attributes', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    expect($execution->id)->not->toBeNull();
    expect($execution->status)->toBe('todo');
    expect($execution->target_type)->toBe(Client::class);
    expect($execution->target_id)->toBe($this->client->id);
});

it('clones checklist items from template when execution is created', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    // Create checklist with items
    $checklist = Checklist::factory()->create([
        'process_task_id' => $processTask->id,
    ]);

    ChecklistItem::factory()->count(3)->create([
        'checklist_id' => $checklist->id,
    ]);

    // Create execution - should clone checklist items
    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    // Verify runtime checklist items were created
    expect($execution->executionItems()->count())->toBe(3);

    $runtimeItems = $execution->executionItems;
    foreach ($runtimeItems as $item) {
        expect($item->is_checked)->toBeFalse();
        expect($item->checked_at)->toBeNull();
    }
});

it('stores snapshot data in runtime checklist items', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $checklist = Checklist::factory()->create([
        'process_task_id' => $processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Verify identity document',
        'action_class' => PromoteClientStatus::class,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    $runtimeItem = $execution->executionItems()->first();

    expect($runtimeItem->checklist_item_id)->toBe($checklistItem->id);
    expect($runtimeItem->is_checked)->toBeFalse();
});

it('links to template task relationship', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    expect($execution->templateTask->id)->toBe($processTask->id);
    expect($execution->processTask->id)->toBe($processTask->id);
});

it('links to client relationship', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    expect($execution->client->id)->toBe($this->client->id);
});

it('supports polymorphic target relationship', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    expect($execution->target)->toBeInstanceOf(Client::class);
    expect($execution->target->id)->toBe($this->client->id);
});

it('can transition between states', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    expect($execution->status)->toBe('todo');

    // Transition to in_progress
    $execution->update([
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    expect($execution->status)->toBe('in_progress');
    expect($execution->started_at)->not->toBeNull();

    // Transition to completed
    $execution->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    expect($execution->status)->toBe('completed');
    expect($execution->completed_at)->not->toBeNull();
});

it('uses soft deletes', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    $executionId = $execution->id;
    $execution->delete();

    expect(TaskExecution::find($executionId))->toBeNull();
    expect(TaskExecution::withTrashed()->find($executionId))->not->toBeNull();
});

it('can check and uncheck runtime checklist items', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $checklist = Checklist::factory()->create([
        'process_task_id' => $processTask->id,
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    $runtimeItem = $execution->executionItems()->first();

    // Check the item
    $runtimeItem->update(['is_checked' => true]);

    expect($runtimeItem->is_checked)->toBeTrue();
    expect($runtimeItem->checked_at)->not->toBeNull();

    // Uncheck the item
    $runtimeItem->update([
        'is_checked' => false,
        'checked_at' => null,
    ]);

    expect($runtimeItem->is_checked)->toBeFalse();
    expect($runtimeItem->checked_at)->toBeNull();
});

it('returns checked items count', function () {
    $processTask = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $this->process->id,
        'sequence_number' => 1,
    ]);

    $checklist = Checklist::factory()->create([
        'process_task_id' => $processTask->id,
    ]);

    ChecklistItem::factory()->count(3)->create([
        'checklist_id' => $checklist->id,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $processTask->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    // Check 2 items
    $execution->executionItems()->limit(2)->update(['is_checked' => true]);

    expect($execution->checkedItems()->where('is_checked', true)->count())->toBe(2);
});
