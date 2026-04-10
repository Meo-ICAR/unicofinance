<?php
namespace App\Actions\Bpm;

use App\Contracts\BpmAction;
use App\Models\TaskExecution;

class UpdateClientToAmlCheck implements BpmAction
{
    public function execute(TaskExecution $execution, array $params = []): void
    {
        $client = $execution->client;

        // Logica Finance: Sposta il cliente nello stato successivo del funnel
        $client->update([
            'status' => 'valutazione_aml',
            'acquired_at' => now(),
        ]);
    }
}
