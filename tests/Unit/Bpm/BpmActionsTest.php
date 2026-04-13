<?php

use App\Actions\Bpm\PromoteClientStatus;
use App\Actions\Bpm\UpdateClientToAmlCheck;
use App\Models\Client;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\TaskExecution;
use App\Contracts\BpmAction;

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

it('implements BpmAction contract', function () {
    $action = new PromoteClientStatus();
    expect($action)->toBeInstanceOf(BpmAction::class);
});

it('can be resolved from container', function () {
    $resolved = app(PromoteClientStatus::class);
    expect($resolved)->toBeInstanceOf(BpmAction::class);
});

it('promotes client status to approvato', function () {
    expect($this->client->status)->toBe('in_trattativa');

    $action = new PromoteClientStatus();
    $action->execute($this->execution);

    $this->client->refresh();
    expect($this->client->status)->toBe('approvato');
});

it('accepts optional reason parameter', function () {
    $action = new PromoteClientStatus();
    $action->execute($this->execution, ['reason' => 'Custom reason']);

    $this->client->refresh();
    expect($this->client->status)->toBe('approvato');
});

it('logs activity when promoting client', function () {
    $action = new PromoteClientStatus();
    $action->execute($this->execution, ['reason' => 'Test reason']);

    $this->client->refresh();

    expect($this->client->activities()->count())->toBeGreaterThan(0);
});

it('UpdateClientToAmlCheck updates client status to valutazione_aml', function () {
    expect($this->client->status)->toBe('in_trattativa');
    expect($this->client->acquired_at)->toBeNull();

    $action = new UpdateClientToAmlCheck();
    $action->execute($this->execution);

    $this->client->refresh();

    expect($this->client->status)->toBe('valutazione_aml');
    expect($this->client->acquired_at)->not->toBeNull();
});

it('UpdateClientToAmlCheck implements BpmAction contract', function () {
    $action = new UpdateClientToAmlCheck();
    expect($action)->toBeInstanceOf(BpmAction::class);
});

it('UpdateClientToAmlCheck can be resolved from container', function () {
    $resolved = app(UpdateClientToAmlCheck::class);
    expect($resolved)->toBeInstanceOf(BpmAction::class);
});
