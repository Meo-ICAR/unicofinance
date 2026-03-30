<?php

namespace App\Contracts;

use App\Models\Client;
use App\Models\TaskExecution;

interface BusinessRule
{
    /**
     * @param  Client  $client  L'anagrafica da valutare
     * @param  TaskExecution|null  $execution  Il contesto dell'esecuzione attuale
     */
    public function evaluate(Client $client, ?TaskExecution $execution = null): bool;
}
