<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;

beforeEach(function () {
    $this->company = Company::factory()->create();
});

it('auto-starts onboarding process when employee is created', function () {
    // Create onboarding process with tasks
    $onboardingProcess = Process::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Employee Onboarding',
        'code' => 'PROC-ONBOARDING',
        'is_active' => true,
        'target_model' => Employee::class,
    ]);

    ProcessTask::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'process_id' => $onboardingProcess->id,
    ]);

    // Create employee
    $employee = Employee::factory()->create([
        'company_id' => $this->company->id,
    ]);

    // Verify task executions were created
    $executions = TaskExecution::where('employee_id', $employee->id)->get();

    expect($executions->count())->toBe(3);

    foreach ($executions as $execution) {
        expect($execution->status)->toBe('todo');
        expect($execution->target_type)->toBe(Employee::class);
        expect($execution->target_id)->toBe($employee->id);
    }
});

it('does not create executions when onboarding process does not exist', function () {
    // Don't create PROC-ONBOARDING process

    $employee = Employee::factory()->create([
        'company_id' => $this->company->id,
    ]);

    // No task executions should be created
    $executions = TaskExecution::where('employee_id', $employee->id)->get();

    expect($executions->count())->toBe(0);
});

it('sets due date for onboarding tasks', function () {
    $onboardingProcess = Process::factory()->create([
        'company_id' => $this->company->id,
        'code' => 'PROC-ONBOARDING',
        'is_active' => true,
        'target_model' => Employee::class,
    ]);

    ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $onboardingProcess->id,
    ]);

    $employee = Employee::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $execution = TaskExecution::where('employee_id', $employee->id)->first();

    expect($execution->due_date)->not->toBeNull();
    expect($execution->due_date->greaterThan(now()))->toBeTrue();
});

it('links task executions to correct process tasks', function () {
    $onboardingProcess = Process::factory()->create([
        'company_id' => $this->company->id,
        'code' => 'PROC-ONBOARDING',
        'is_active' => true,
        'target_model' => Employee::class,
    ]);

    $task1 = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $onboardingProcess->id,
        'sequence_number' => 1,
    ]);

    $task2 = ProcessTask::factory()->create([
        'company_id' => $this->company->id,
        'process_id' => $onboardingProcess->id,
        'sequence_number' => 2,
    ]);

    $employee = Employee::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $executions = TaskExecution::where('employee_id', $employee->id)->get();

    $processTaskIds = $executions->pluck('process_task_id')->sort()->values();
    $expectedIds = collect([$task1->id, $task2->id])->sort()->values();

    expect($processTaskIds)->toEqual($expectedIds);
});
