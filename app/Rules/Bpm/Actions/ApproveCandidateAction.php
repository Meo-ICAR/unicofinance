<?php

declare(strict_types=1);

namespace App\Rules\Bpm\Actions;

use App\Contracts\BpmAction;
use App\Contracts\BpmActionInterface;
use App\Models\Agent;
use App\Models\TaskExecution;
use Illuminate\Support\Facades\Log;

/**
 * ApproveCandidateAction
 *
 * Transitions the Agent's status to 'approvato'.
 *
 * This action is idempotent — calling it on an already-approved agent
 * is a no-op (only logs a warning).
 *
 * Registered in checklist_items.action_class as:
 *   App\Rules\Bpm\Actions\ApproveCandidateAction
 */
final class ApproveCandidateAction implements BpmAction, BpmActionInterface
{
    use ResolvesTargetAgent;

    public function execute(TaskExecution $execution, array $params = []): void
    {
        $agent = $this->resolveAgent($execution);

        if ($agent->status === Agent::STATUS_APPROVATO) {
            Log::info('ApproveCandidateAction: agent is already approved', [
                'agent_id' => $agent->id,
            ]);

            return;
        }

        // Block transition from a rejected state
        if ($agent->status === Agent::STATUS_RIFIUTATO) {
            throw new \RuntimeException(
                "Cannot approve agent #{$agent->id} — status is 'rifiutato'."
            );
        }

        $agent->update(['status' => Agent::STATUS_APPROVATO]);

        Log::info('ApproveCandidateAction: agent approved', [
            'agent_id' => $agent->id,
            'agent_name' => $agent->full_name,
        ]);
    }
}
