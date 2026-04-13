<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\RaciAssignment;

beforeEach(function () {
    $this->company = Company::factory()->create();
});

it('creates process with correct attributes', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Client Onboarding',
        'is_active' => true,
        'version' => 1,
    ]);

    expect($process->id)->not->toBeNull();
    expect($process->name)->toBe('Client Onboarding');
    expect($process->is_active)->toBeTrue();
    expect($process->version)->toBe(1);
});

it('uses soft deletes for process templates', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    $processId = $process->id;
    $process->delete();

    expect(Process::find($processId))->toBeNull();
    expect(Process::withTrashed()->find($processId))->not->toBeNull();
});

it('maintains process tasks relationship', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
    ]);

    ProcessTask::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
    ]);

    expect($process->tasks)->toHaveCount(3);
});

it('orders tasks by sequence number', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
    ]);

    ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
        'sequence_number' => 3,
    ]);

    ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
        'sequence_number' => 1,
    ]);

    ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
        'sequence_number' => 2,
    ]);

    $tasks = $process->tasks;
    expect($tasks[0]->sequence_number)->toBe(1);
    expect($tasks[1]->sequence_number)->toBe(2);
    expect($tasks[2]->sequence_number)->toBe(3);
});

it('auto-creates RACI assignments when process task is created', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $task = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
    ]);

    // Should have 4 RACI assignments: R, A, C, I
    $raciAssignments = RaciAssignment::where('process_task_id', $task->id)->get();

    expect($raciAssignments)->toHaveCount(4);

    $roles = $raciAssignments->pluck('role')->sort()->values();
    expect($roles->toArray())->toBe(['A', 'C', 'I', 'R']);
});

it('supports process versioning', function () {
    $processV1 = Process::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Client Onboarding',
        'version' => 1,
        'is_active' => true,
    ]);

    // Deactivate v1
    $processV1->update(['is_active' => false]);

    // Create v2
    $processV2 = Process::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Client Onboarding',
        'version' => 2,
        'is_active' => true,
    ]);

    expect($processV1->version)->toBe(1);
    expect($processV1->is_active)->toBeFalse();
    expect($processV2->version)->toBe(2);
    expect($processV2->is_active)->toBeTrue();

    // Both should exist in database
    expect(Process::where('name', 'Client Onboarding')->count())->toBe(2);
});

it('links to company, business function, and macro category', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
    ]);

    expect($process->company)->not->toBeNull();
    expect($process->company->id)->toBe($this->company->id);
    expect($process->ownerFunction)->not->toBeNull();
    expect($process->processMacroCategory)->not->toBeNull();
});

it('can be activated and deactivated', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => false,
    ]);

    expect($process->is_active)->toBeFalse();

    $process->update(['is_active' => true]);
    expect($process->fresh()->is_active)->toBeTrue();

    $process->update(['is_active' => false]);
    expect($process->fresh()->is_active)->toBeFalse();
});

it('supports request type mappings', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
    ]);

    \App\Models\ProcessRequestMapping::create([
        'request_type' => 'gdpr_access',
        'process_id' => $process->id,
        'is_suggested' => true,
    ]);

    expect($process->requestMappings)->toHaveCount(1);
    expect($process->requestMappings->first()->request_type)->toBe('gdpr_access');
});

it('scopes active processes with request type', function () {
    $process1 = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    $process2 = Process::factory()->create([
        'company_id' => $this->company->id,
        'is_active' => false,
    ]);

    \App\Models\ProcessRequestMapping::create([
        'request_type' => 'gdpr_access',
        'process_id' => $process1->id,
        'is_suggested' => true,
    ]);

    $activeProcesses = Process::scopeActiveWithRequestType('gdpr_access')->get();

    expect($activeProcesses->pluck('id'))->toContain($process1->id);
    expect($activeProcesses->pluck('id'))->not->toContain($process2->id);
});

it('creates nested checklists and items via template structure', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $task = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
    ]);

    $checklist1 = Checklist::factory()->create([
        'process_task_id' => $task->id,
        'name' => 'Verification Steps',
    ]);

    ChecklistItem::factory()->count(2)->create([
        'checklist_id' => $checklist1->id,
    ]);

    $checklist2 = Checklist::factory()->create([
        'process_task_id' => $task->id,
        'name' => 'Documentation Steps',
    ]);

    ChecklistItem::factory()->count(3)->create([
        'checklist_id' => $checklist2->id,
    ]);

    expect($task->checklists)->toHaveCount(2);
    expect($checklist1->items)->toHaveCount(2);
    expect($checklist2->items)->toHaveCount(3);
});

it('supports privacy data attachments to process tasks', function () {
    $process = Process::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $task = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $process->id,
    ]);

    $privacyDataType = \App\Models\PrivacyDataType::factory()->create();
    $privacyLegalBase = \App\Models\PrivacyLegalBase::factory()->create();

    \App\Models\ProcessTaskPrivacyData::create([
        'process_task_id' => $task->id,
        'privacy_data_type_id' => $privacyDataType->id,
        'privacy_legal_base_id' => $privacyLegalBase->id,
        'access_level' => 'restricted',
        'purpose' => 'Client verification',
        'is_encrypted' => true,
        'is_shared_externally' => false,
    ]);

    expect($task->privacyData)->toHaveCount(1);
    expect($task->privacyData->first()->purpose)->toBe('Client verification');
});
