<?php

use App\Actions\Bpm\PromoteClientStatus;
use App\Actions\Bpm\UpdateClientToAmlCheck;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;
use App\Models\TaskExecution;
use App\Models\TaskExecutionChecklistItem;
use App\Rules\Bpm\ForeignerRule;
use App\Services\BpmEngineService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'in_trattativa',
    ]);
});

it('completes full process lifecycle: template → execution → completion', function () {
    // ═══════════════════════════════════════════════════════════
    // PHASE 1: TEMPLATE DESIGN
    // ═══════════════════════════════════════════════════════════

    // Create process template
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Client Onboarding',
        'is_active' => true,
        'version' => 1,
        'target_model' => Client::class,
    ]);

    // Create process tasks
    $task1 = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
        'sequence_number' => 1,
        'name' => 'Verify Identity',
    ]);

    $task2 = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
        'sequence_number' => 2,
        'name' => 'AML Check',
    ]);

    // Create checklists with items
    $checklist1 = Checklist::factory()->create([
        'process_task_id' => $task1->id,
        'name' => 'Identity Verification Steps',
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklist1->id,
        'instruction' => 'Check government ID',
        'is_mandatory' => true,
        'action_class' => PromoteClientStatus::class,
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklist1->id,
        'instruction' => 'Verify address',
        'is_mandatory' => false,
    ]);

    $checklist2 = Checklist::factory()->create([
        'process_task_id' => $task2->id,
        'name' => 'AML Verification',
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklist2->id,
        'instruction' => 'Run AML screening',
        'is_mandatory' => true,
        'action_class' => UpdateClientToAmlCheck::class,
    ]);

    // Verify template structure
    expect($process->tasks)->toHaveCount(2);
    expect($task1->checklists)->toHaveCount(1);
    expect($task2->checklists)->toHaveCount(1);
    expect($checklist1->items)->toHaveCount(2);
    expect($checklist2->items)->toHaveCount(1);

    // Verify RACI assignments were auto-created
    expect(RaciAssignment::where('process_task_id', $task1->id)->count())->toBe(4); // R, A, C, I
    expect(RaciAssignment::where('process_task_id', $task2->id)->count())->toBe(4);

    // ═══════════════════════════════════════════════════════════
    // PHASE 2: PROCESS START (TEMPLATE → EXECUTION)
    // ═══════════════════════════════════════════════════════════

    // Start process for client - creates task executions
    $execution1 = TaskExecution::create([
        'process_task_id' => $task1->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    $execution2 = TaskExecution::create([
        'process_task_id' => $task2->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    // Verify runtime executions were created
    expect($execution1->id)->not->toBeNull();
    expect($execution2->id)->not->toBeNull();

    // Verify checklist items were cloned from template
    expect($execution1->executionItems)->toHaveCount(2);
    expect($execution2->executionItems)->toHaveCount(1);

    // Verify runtime items are unchecked
    foreach ($execution1->executionItems as $item) {
        expect($item->is_checked)->toBeFalse();
        expect($item->checked_at)->toBeNull();
    }

    // ═══════════════════════════════════════════════════════════
    // PHASE 3: EXECUTION PROGRESSION
    // ═══════════════════════════════════════════════════════════

    // Start first task
    $execution1->update([
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    expect($execution1->status)->toBe('in_progress');
    expect($execution1->started_at)->not->toBeNull();

    // Complete checklist items
    $runtimeItem1 = $execution1->executionItems()->first();
    $runtimeItem2 = $execution1->executionItems()->skip(1)->first();

    // Complete first item - should trigger PromoteClientStatus action
    $runtimeItem1->update(['is_checked' => true]);

    expect($runtimeItem1->is_checked)->toBeTrue();
    expect($runtimeItem1->checked_at)->not->toBeNull();

    // Verify action was executed
    $this->client->refresh();
    expect($this->client->status)->toBe('approvato');

    // Complete second item (no action)
    $runtimeItem2->update(['is_checked' => true]);

    expect($runtimeItem2->is_checked)->toBeTrue();

    // Complete first task
    $execution1->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    expect($execution1->status)->toBe('completed');
    expect($execution1->completed_at)->not->toBeNull();

    // Start second task
    $execution2->update([
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    // Complete AML checklist item
    $amlItem = $execution2->executionItems()->first();
    $amlItem->update(['is_checked' => true]);

    expect($amlItem->is_checked)->toBeTrue();

    // Verify AML action was executed
    $this->client->refresh();
    expect($this->client->status)->toBe('valutazione_aml');
    expect($this->client->acquired_at)->not->toBeNull();

    // Complete second task
    $execution2->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    expect($execution2->status)->toBe('completed');

    // ═══════════════════════════════════════════════════════════
    // PHASE 4: VERIFICATION & AUDIT
    // ═══════════════════════════════════════════════════════════

    // Verify all executions completed
    expect(TaskExecution::where('client_id', $this->client->id)
        ->where('status', 'completed')
        ->count())->toBe(2);

    // Verify client status was updated by actions
    expect($this->client->status)->toBe('valutazione_aml');

    // Verify checklist completion rate
    $totalItems = TaskExecutionChecklistItem::whereIn('task_execution_id', [$execution1->id, $execution2->id])->count();
    $completedItems = TaskExecutionChecklistItem::whereIn('task_execution_id', [$execution1->id, $execution2->id])
        ->where('is_checked', true)
        ->count();

    expect($totalItems)->toBe(3);
    expect($completedItems)->toBe(3);
});

it('respects skip conditions during execution', function () {
    // Create process with conditional skip
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
        'target_model' => Client::class,
    ]);

    $task = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
    ]);

    $checklist = Checklist::factory()->create([
        'process_task_id' => $task->id,
    ]);

    // This item should be skipped for Italian clients
    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Check passport (foreigners only)',
        'skip_condition_class' => ForeignerRule::class,
    ]);

    // Italian client
    $italianClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'citizenship' => 'IT',
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $task->id,
        'client_id' => $italianClient->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $italianClient->id,
    ]);

    // Use engine service to evaluate checklist
    $service = new BpmEngineService();
    $evaluated = $service->getEvaluatedChecklist($execution);

    // Item should be present (skip condition is false for IT client)
    expect($evaluated->count())->toBe(1);

    // Foreign client
    $foreignClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'citizenship' => 'US',
    ]);

    $foreignExecution = TaskExecution::create([
        'process_task_id' => $task->id,
        'client_id' => $foreignClient->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $foreignClient->id,
    ]);

    $evaluatedForeign = $service->getEvaluatedChecklist($foreignExecution);

    // Item should be skipped (skip condition is true for US client)
    expect($evaluatedForeign->count())->toBe(0);
});

it('enforces require conditions during execution', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
        'target_model' => Client::class,
    ]);

    $task = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
    ]);

    $checklist = Checklist::factory()->create([
        'process_task_id' => $task->id,
    ]);

    // Non-mandatory item with require condition
    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Additional verification for foreigners',
        'is_mandatory' => false,
        'require_condition_class' => ForeignerRule::class,
    ]);

    $service = new BpmEngineService();

    // Italian client - should NOT be mandatory
    $italianClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'citizenship' => 'IT',
    ]);

    $italianExecution = TaskExecution::create([
        'process_task_id' => $task->id,
        'client_id' => $italianClient->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $italianClient->id,
    ]);

    $evaluatedItalian = $service->getEvaluatedChecklist($italianExecution);
    expect($evaluatedItalian->first()->is_mandatory)->toBeFalse();

    // Foreign client - should be mandatory
    $foreignClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'citizenship' => 'FR',
    ]);

    $foreignExecution = TaskExecution::create([
        'process_task_id' => $task->id,
        'client_id' => $foreignClient->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $foreignClient->id,
    ]);

    $evaluatedForeign = $service->getEvaluatedChecklist($foreignExecution);
    expect($evaluatedForeign->first()->is_mandatory)->toBeTrue();
});

it('supports process versioning without affecting existing executions', function () {
    // Create version 1
    $processV1 = Process::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Client Onboarding',
        'is_active' => true,
        'version' => 1,
        'target_model' => Client::class,
    ]);

    $taskV1 = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $processV1->id,
    ]);

    $checklistV1 = Checklist::factory()->create([
        'process_task_id' => $taskV1->id,
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklistV1->id,
        'instruction' => 'Original instruction',
    ]);

    // Create execution from v1
    $executionV1 = TaskExecution::create([
        'process_task_id' => $taskV1->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    // Create version 2 (deactivate v1)
    $processV1->update(['is_active' => false]);

    $processV2 = Process::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Client Onboarding',
        'is_active' => true,
        'version' => 2,
        'target_model' => Client::class,
    ]);

    $taskV2 = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $processV2->id,
    ]);

    $checklistV2 = Checklist::factory()->create([
        'process_task_id' => $taskV2->id,
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklistV2->id,
        'instruction' => 'Updated instruction',
    ]);

    // V1 execution should still reference V1 task
    expect($executionV1->processTask->process->version)->toBe(1);
    expect($executionV1->processTask->process->id)->toBe($processV1->id);

    // New execution should use V2
    $executionV2 = TaskExecution::create([
        'process_task_id' => $taskV2->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    expect($executionV2->processTask->process->version)->toBe(2);
    expect($executionV2->processTask->process->id)->toBe($processV2->id);
});

it('maintains audit trail through process lifecycle', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
        'target_model' => Client::class,
    ]);

    $task = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
    ]);

    $checklist = Checklist::factory()->create([
        'process_task_id' => $task->id,
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Verify identity',
        'action_class' => PromoteClientStatus::class,
    ]);

    $execution = TaskExecution::create([
        'process_task_id' => $task->id,
        'client_id' => $this->client->id,
        'status' => 'todo',
        'target_type' => Client::class,
        'target_id' => $this->client->id,
    ]);

    // Progress through states
    $execution->update(['status' => 'in_progress', 'started_at' => now()]);

    $runtimeItem = $execution->executionItems()->first();
    $runtimeItem->update(['is_checked' => true]);

    $execution->update(['status' => 'completed', 'completed_at' => now()]);

    // Verify audit trail exists
    expect($execution->status)->toBe('completed');
    expect($execution->started_at)->not->toBeNull();
    expect($execution->completed_at)->not->toBeNull();
    expect($runtimeItem->fresh()->checked_at)->not->toBeNull();
});
