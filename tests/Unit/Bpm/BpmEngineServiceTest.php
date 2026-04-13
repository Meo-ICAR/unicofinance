<?php

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;
use App\Rules\Bpm\ForeignerRule;
use App\Services\BpmEngineService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'citizenship' => 'IT',
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

    $this->service = new BpmEngineService();
});

it('returns available actions for task execution', function () {
    $actions = $this->service->getAvailableActions($this->execution);

    expect($actions)->toBeCollection();
});

it('returns available conditions for task execution', function () {
    $conditions = $this->service->getAvailableConditions($this->execution);

    expect($conditions)->toBeCollection();
});

it('evaluates checklist and returns enriched items', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Verify identity document',
        'is_mandatory' => true,
    ]);

    $evaluated = $this->service->getEvaluatedChecklist($this->execution);

    expect($evaluated)->toBeCollection();
    expect($evaluated->count())->toBeGreaterThan(0);

    $firstItem = $evaluated->first();
    expect($firstItem)->toHaveProperty('instruction');
    expect($firstItem)->toHaveProperty('is_mandatory');
});

it('skips checklist items when skip condition is met', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    // Italian client - should NOT be skipped
    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Check passport (for foreigners)',
        'skip_condition_class' => ForeignerRule::class,
    ]);

    $evaluated = $this->service->getEvaluatedChecklist($this->execution);

    // Item should be present (citizenship is IT, so skip condition is false)
    expect($evaluated->count())->toBe(1);
});

it('skips checklist items when skip condition is met for foreigners', function () {
    $foreignClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'citizenship' => 'US',
    ]);

    $foreignExecution = TaskExecution::create([
        'process_task_id' => $this->processTask->id,
        'client_id' => $foreignClient->id,
        'status' => 'in_progress',
        'target_type' => Client::class,
        'target_id' => $foreignClient->id,
    ]);

    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    // Foreign client - should be skipped
    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Check Italian documents',
        'skip_condition_class' => ForeignerRule::class,
    ]);

    $evaluated = $this->service->getEvaluatedChecklist($foreignExecution);

    // Item should be skipped (citizenship is US, so skip condition is true)
    expect($evaluated->count())->toBe(0);
});

it('makes items mandatory when require condition is met', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    // Non-mandatory item with require condition
    ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Additional verification for foreigners',
        'is_mandatory' => false,
        'require_condition_class' => ForeignerRule::class,
    ]);

    // Italian client - should NOT be mandatory
    $evaluated = $this->service->getEvaluatedChecklist($this->execution);
    $item = $evaluated->first();
    expect($item->is_mandatory)->toBeFalse();

    // Foreign client - should be mandatory
    $foreignClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'citizenship' => 'FR',
    ]);

    $foreignExecution = TaskExecution::create([
        'process_task_id' => $this->processTask->id,
        'client_id' => $foreignClient->id,
        'status' => 'in_progress',
        'target_type' => Client::class,
        'target_id' => $foreignClient->id,
    ]);

    $evaluatedForeign = $this->service->getEvaluatedChecklist($foreignExecution);
    $itemForeign = $evaluatedForeign->first();
    expect($itemForeign->is_mandatory)->toBeTrue();
});

it('completes a checklist item successfully', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Test instruction',
    ]);

    // Create runtime checklist item
    $runtimeItem = $this->execution->executionItems()->create([
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    $result = $this->service->completeChecklistItem($this->execution->id, $checklistItem->id);

    expect($result['success'])->toBeTrue();
    expect($result['action_class'])->toBeNull();

    $runtimeItem->refresh();
    expect($runtimeItem->is_checked)->toBeTrue();
});

it('throws exception when checklist item not found', function () {
    $this->service->completeChecklistItem($this->execution->id, 99999);
})->throws(RuntimeException::class);

it('returns success when item is already checked', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Test instruction',
    ]);

    $runtimeItem = $this->execution->executionItems()->create([
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => true,
    ]);

    $result = $this->service->completeChecklistItem($this->execution->id, $checklistItem->id);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toContain('already checked');
});

it('unchecks a previously checked item', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Test instruction',
    ]);

    $runtimeItem = $this->execution->executionItems()->create([
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => true,
        'checked_at' => now(),
    ]);

    $result = $this->service->uncheckChecklistItem($this->execution->id, $checklistItem->id);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Item unchecked.');

    $runtimeItem->refresh();
    expect($runtimeItem->is_checked)->toBeFalse();
    expect($runtimeItem->checked_at)->toBeNull();
});

it('returns success when unchecking an already unchecked item', function () {
    $checklist = Checklist::factory()->create([
        'process_task_id' => $this->processTask->id,
    ]);

    $checklistItem = ChecklistItem::factory()->create([
        'checklist_id' => $checklist->id,
        'instruction' => 'Test instruction',
    ]);

    $runtimeItem = $this->execution->executionItems()->create([
        'checklist_item_id' => $checklistItem->id,
        'is_checked' => false,
    ]);

    $result = $this->service->uncheckChecklistItem($this->execution->id, $checklistItem->id);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Item is not checked.');
});
