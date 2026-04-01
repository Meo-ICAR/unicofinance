<?php

namespace App\Rules\Bpm;

use App\Contracts\BusinessRule;
use App\Models\Client;
use App\Models\TaskExecution;

class ForeignerRule implements BusinessRule
{
    public function evaluate(Client $client, ?TaskExecution $execution = null): bool
    {
        // Logica: il controllo è richiesto se la cittadinanza non è Italiana
        return $client->citizenship !== 'IT';
    }
}
