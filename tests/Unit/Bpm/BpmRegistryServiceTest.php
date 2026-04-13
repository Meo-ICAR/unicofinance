<?php

use App\Services\BpmRegistryService;

beforeEach(function () {
    config(['bpm_registry.actions' => [
        \App\Actions\Bpm\PromoteClientStatus::class => [
            'name' => 'Promote Client Status',
            'group' => 'Client Management',
            'companies' => null,
        ],
        \App\Actions\Bpm\UpdateClientToAmlCheck::class => [
            'name' => 'Update Client to AML Check',
            'group' => 'Compliance',
            'companies' => [1, 2, 3],
        ],
    ]]);

    config(['bpm_registry.conditions' => [
        \App\Rules\Bpm\ForeignerRule::class => [
            'name' => 'Foreigner Rule',
            'group' => 'Compliance',
            'companies' => null,
        ],
    ]]);
});

it('returns all actions when companies is null', function () {
    $options = BpmRegistryService::getOptionsForFilament('actions', '99');

    expect($options)->toHaveKey('Client Management');
    expect($options['Client Management'])->toHaveKey(\App\Actions\Bpm\PromoteClientStatus::class);
});

it('filters actions by company', function () {
    $options = BpmRegistryService::getOptionsForFilament('actions', '1');

    expect($options)->toHaveKey('Client Management');
    expect($options)->toHaveKey('Compliance');
    expect($options['Compliance'])->toHaveKey(\App\Actions\Bpm\UpdateClientToAmlCheck::class);
});

it('excludes actions when company is not in allowed list', function () {
    $options = BpmRegistryService::getOptionsForFilament('actions', '99');

    expect($options)->toHaveKey('Client Management');
    expect($options)->not->toHaveKey('Compliance');
});

it('groups actions by their configured group', function () {
    $options = BpmRegistryService::getOptionsForFilament('actions', '1');

    expect($options)->toHaveKey('Client Management');
    expect($options)->toHaveKey('Compliance');
});

it('returns empty array for non-existent type', function () {
    $options = BpmRegistryService::getOptionsForFilament('non_existent', '1');

    expect($options)->toBeArray();
    expect($options)->toBeEmpty();
});

it('returns conditions filtered by company', function () {
    $options = BpmRegistryService::getOptionsForFilament('conditions', '1');

    expect($options)->toHaveKey('Compliance');
    expect($options['Compliance'])->toHaveKey(\App\Rules\Bpm\ForeignerRule::class);
});

it('uses default group when not specified', function () {
    config(['bpm_registry.test_actions' => [
        \App\Actions\Bpm\PromoteClientStatus::class => [
            'name' => 'Test Action',
            'companies' => null,
        ],
    ]]);

    $options = BpmRegistryService::getOptionsForFilament('test_actions', '1');

    expect($options)->toHaveKey('Generale');
});
