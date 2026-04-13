<?php

use App\Models\Client;
use App\Models\Company;
use App\Rules\Bpm\ForeignerRule;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->rule = new ForeignerRule();
});

it('returns true for non-Italian citizenship', function () {
    $client = Client::factory()->make([
        'company_id' => $this->company->id,
        'citizenship' => 'FR',
    ]);

    expect($this->rule->evaluate($client))->toBeTrue();
});

it('returns false for Italian citizenship', function () {
    $client = Client::factory()->make([
        'company_id' => $this->company->id,
        'citizenship' => 'IT',
    ]);

    expect($this->rule->evaluate($client))->toBeFalse();
});

it('implements BusinessRule contract', function () {
    expect($this->rule)->toBeInstanceOf(\App\Contracts\BusinessRule::class);
});

it('can be resolved from container', function () {
    $resolved = app(ForeignerRule::class);
    expect($resolved)->toBeInstanceOf(\App\Contracts\BusinessRule::class);
});
