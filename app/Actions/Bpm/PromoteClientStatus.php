<?php
namespace App\Actions\Bpm;

use App\Contracts\BpmAction;
use App\Models\TaskExecution;

class PromoteClientStatus implements BpmAction
{
    public function execute(TaskExecution $execution, array $params = []): void
    {
        $client = $execution->client;

        // Se l'automazione passa un 'reason', lo logghiamo
        $reason = $params['reason'] ?? 'Completamento task standard';

        $client->update(['status' => 'approvato']);

        activity()->performedOn($client)->log("Promosso per: $reason");
    }
}
