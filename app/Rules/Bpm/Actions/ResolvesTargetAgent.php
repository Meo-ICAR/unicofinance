<?php

declare(strict_types=1);

namespace App\Rules\Bpm\Actions;

use App\Models\Agent;
use App\Models\TaskExecution;
use RuntimeException;

/**
 * ResolvesTargetAgent — shared trait for all Agent-related BPM actions.
 *
 * Every action in this directory needs to resolve the `Agent` instance from
 * the polymorphic `TaskExecution->target` relationship.  This trait
 * centralises that logic so each action class stays focused on its single
 * responsibility.
 */
trait ResolvesTargetAgent
{
    /**
     * Resolve the Agent from the TaskExecution's polymorphic target.
     *
     * @throws RuntimeException  If the target is not an Agent
     */
    protected function resolveAgent(TaskExecution $execution): Agent
    {
        // Fast path: target is already loaded as an Agent
        if ($execution->target instanceof Agent) {
            return $execution->target;
        }

        // Fallback: resolve via target_type / target_id
        if ($execution->target_type === Agent::class && $execution->target_id) {
            $agent = Agent::find($execution->target_id);
            if ($agent instanceof Agent) {
                return $agent;
            }
        }

        throw new RuntimeException(sprintf(
            'TaskExecution target is not an Agent. Got: %s (ID: %s)',
            $execution->target_type ?? 'null',
            $execution->target_id ?? 'null',
        ));
    }
}
