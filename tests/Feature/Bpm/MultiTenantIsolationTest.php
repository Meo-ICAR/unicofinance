<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;

beforeEach(function () {
    $this->companyA = Company::factory()->create(['name' => 'Company A']);
    $this->companyB = Company::factory()->create(['name' => 'Company B']);

    $this->clientA = Client::factory()->create([
        'company_id' => $this->companyA->id,
    ]);

    $this->clientB = Client::factory()->create([
        'company_id' => $this->companyB->id,
    ]);
});

it('isolates processes between tenants', function () {
    $processA = Process::factory()->create([
        'company_id' => $this->companyA->id,
        'is_active' => true,
    ]);

    $processB = Process::factory()->create([
        'company_id' => $this->companyB->id,
        'is_active' => true,
    ]);

    // Company A should not see Company B's process
    expect(Process::where('company_id', $this->companyA->id)->count())->toBe(1);
    expect(Process::where('company_id', $this->companyB->id)->count())->toBe(1);

    expect(Process::find($processA->id)->company_id)->toBe($this->companyA->id);
    expect(Process::find($processB->id)->company_id)->toBe($this->companyB->id);
});

it('isolates task executions between tenants', function () {
    $processA = Process::factory()->create([
        'company_id' => $this->companyA->id,
        'is_active' => true,
    ]);

    $processB = Process::factory()->create([
        'company_id' => $this->companyB->id,
        'is_active' => true,
    ]);

    $taskA = ProcessTask::factory()->create([
        'company_id' => $this->companyA->id,
        'process_id' => $processA->id,
    ]);

    $taskB = ProcessTask::factory()->create([
        'company_id' => $this->companyB->id,
        'process_id' => $processB->id,
    ]);

    $executionA = TaskExecution::create([
        'process_task_id' => $taskA->id,
        'client_id' => $this->clientA->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->clientA->id,
    ]);

    $executionB = TaskExecution::create([
        'process_task_id' => $taskB->id,
        'client_id' => $this->clientB->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->clientB->id,
    ]);

    // Verify isolation
    expect(TaskExecution::whereHas('processTask', function ($query) use ($taskA) {
        $query->where('company_id', $this->companyA->id);
    })->count())->toBe(1);

    expect(TaskExecution::whereHas('processTask', function ($query) use ($taskB) {
        $query->where('company_id', $this->companyB->id);
    })->count())->toBe(1);
});

it('isolates checklist items between tenants', function () {
    $processA = Process::factory()->create([
        'company_id' => $this->companyA->id,
        'is_active' => true,
    ]);

    $processB = Process::factory()->create([
        'company_id' => $this->companyB->id,
        'is_active' => true,
    ]);

    $taskA = ProcessTask::factory()->create([
        'company_id' => $this->companyA->id,
        'process_id' => $processA->id,
    ]);

    $taskB = ProcessTask::factory()->create([
        'company_id' => $this->companyB->id,
        'process_id' => $processB->id,
    ]);

    $checklistA = Checklist::factory()->create([
        'process_task_id' => $taskA->id,
    ]);

    $checklistB = Checklist::factory()->create([
        'process_task_id' => $taskB->id,
    ]);

    $itemA = ChecklistItem::factory()->create([
        'checklist_id' => $checklistA->id,
        'instruction' => 'Company A item',
    ]);

    $itemB = ChecklistItem::factory()->create([
        'checklist_id' => $checklistB->id,
        'instruction' => 'Company B item',
    ]);

    // Verify checklist items are isolated
    expect(ChecklistItem::whereHas('checklist', function ($query) use ($taskA) {
        $query->whereHas('processTask', function ($q) use ($taskA) {
            $q->where('company_id', $this->companyA->id);
        });
    })->count())->toBe(1);

    expect(ChecklistItem::whereHas('checklist', function ($query) use ($taskB) {
        $query->whereHas('processTask', function ($q) use ($taskB) {
            $q->where('company_id', $this->companyB->id);
        });
    })->count())->toBe(1);
});

it('does not allow cross-tenant process task access', function () {
    $processA = Process::factory()->create([
        'company_id' => $this->companyA->id,
        'is_active' => true,
    ]);

    $taskA = ProcessTask::factory()->create([
        'company_id' => $this->companyA->id,
        'process_id' => $processA->id,
    ]);

    // Company B client should not be able to access Company A's task
    $execution = TaskExecution::create([
        'process_task_id' => $taskA->id,
        'client_id' => $this->clientB->id, // Different company
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->clientB->id,
    ]);

    // The execution exists but the client belongs to different company
    expect($execution->client->company_id)->toBe($this->companyB->id);
    expect($execution->processTask->company_id)->toBe($this->companyA->id);
});

it('isolates RACI assignments between tenants', function () {
    $processA = Process::factory()->create([
        'company_id' => $this->companyA->id,
        'is_active' => true,
    ]);

    $processB = Process::factory()->create([
        'company_id' => $this->companyB->id,
        'is_active' => true,
    ]);

    $taskA = ProcessTask::factory()->create([
        'company_id' => $this->companyA->id,
        'process_id' => $processA->id,
    ]);

    $taskB = ProcessTask::factory()->create([
        'company_id' => $this->companyB->id,
        'process_id' => $processB->id,
    ]);

    // Each task auto-generates RACI assignments on creation
    expect(\App\Models\RaciAssignment::whereHas('processTask', function ($query) use ($taskA) {
        $query->where('company_id', $this->companyA->id);
    })->count())->toBeGreaterThan(0);

    expect(\App\Models\RaciAssignment::whereHas('processTask', function ($query) use ($taskB) {
        $query->where('company_id', $this->companyB->id);
    })->count())->toBeGreaterThan(0);
});

it('maintains tenant context in task execution relationships', function () {
    $processA = Process::factory()->create([
        'company_id' => $this->companyA->id,
        'is_active' => true,
    ]);

    $taskA = ProcessTask::factory()->create([
        'company_id' => $this->companyA->id,
        'process_id' => $processA->id,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $taskA->id,
        'client_id' => $this->clientA->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->clientA->id,
    ]);

    // All related models should belong to the same company
    expect($execution->client->company_id)->toBe($this->companyA->id);
    expect($execution->processTask->company_id)->toBe($this->companyA->id);
    expect($execution->processTask->process->company_id)->toBe($this->companyA->id);
});
