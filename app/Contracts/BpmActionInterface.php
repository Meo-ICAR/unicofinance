<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\TaskExecution;

/**
 * BpmActionInterface — the canonical contract for BPM action classes.
 *
 * Defines the single method every BPM action must implement. When a user
 * checks a checklist item in the UI, the observer resolves the action_class
 * FQN, instantiates the class, and calls this method.
 *
 * Implementations are expected to resolve their specific target model
 * (e.g. Agent, Proforma, Client) from $execution->target (polymorphic)
 * or from a known relationship on the execution.
 *
 * @see BpmAction  Alias of this interface for backward compatibility
 */
interface BpmActionInterface
{
    /**
     * Execute the side-effect for the given BPM execution context.
     *
     * @param  TaskExecution    $execution  The runtime task execution
     * @param  array<string,mixed> $params  Optional parameters passed by the engine
     *
     * @throws \RuntimeException  If validation fails — the observer catches this
     *                            and prevents the checklist item from being checked.
     */
    public function execute(TaskExecution $execution, array $params = []): void;
}
