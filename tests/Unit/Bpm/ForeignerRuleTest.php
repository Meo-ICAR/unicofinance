<?php

use App\Models\Client;
use App\Rules\Bpm\ForeignerRule;

beforeEach(function () {
    $this->rule = new ForeignerRule();
});

it('returns true when citizenship is not Italian', function () {
    $client = new Client(['citizenship' => 'FR']);

    expect($this->rule->evaluate($client))->toBeTrue();
});

it('returns true when citizenship is null or missing', function () {
    $client = new Client();

    // When citizenship is null, it's not 'IT', so rule returns true
    expect($this->rule->evaluate($client))->toBeTrue();
});

it('implements BusinessRule contract', function () {
    expect($this->rule)->toBeInstanceOf(\App\Contracts\BusinessRule::class);
});

it('can be resolved from container', function () {
    $resolved = app(ForeignerRule::class);
    expect($resolved)->toBeInstanceOf(\App\Contracts\BusinessRule::class);
});

it('evaluates citizenship attribute correctly', function () {
    $clientIT = new Client();
    $clientIT->citizenship = 'IT';

    $clientFR = new Client();
    $clientFR->citizenship = 'FR';

    expect($this->rule->evaluate($clientIT))->toBeFalse();
    expect($this->rule->evaluate($clientFR))->toBeTrue();
});
