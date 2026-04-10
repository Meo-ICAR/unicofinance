<?php

namespace App\Rules\Bpm\Actions;

use App\Contracts\BpmAction;
use App\Models\Proforma;
use App\Models\TaskExecution;
use RuntimeException;

/**
 * ValidateCommissionsTotalAction
 *
 * Validates that the sum of all Commission amounts associated with the
 * TaskExecution's target Proforma matches the Proforma's total_amount.
 *
 * If the totals don't match (within a small tolerance for floating-point),
 * the action throws a RuntimeException — preventing the checklist item
 * from being marked as completed.
 *
 * Registered in checklist_items.action_class as:
 *   App\Rules\Bpm\Actions\ValidateCommissionsTotalAction
 */
class ValidateCommissionsTotalAction implements BpmAction
{
    /**
     * Tolerance in cents to avoid floating-point issues.
     */
    private const TOLERANCE = 0.01;

    public function execute(TaskExecution $execution, array $params = []): void
    {
        $proforma = $this->resolveProforma($execution);

        $totalCommissions = $proforma->commissions()->sum('amount');
        $proformaTotal = (float) $proforma->total_amount;

        if (abs($totalCommissions - $proformaTotal) > self::TOLERANCE) {
            throw new RuntimeException(sprintf(
                'Commission total (%.2f) does not match Proforma #%s total (%.2f). '
                . 'Difference: %.2f. Please review all commission entries before proceeding.',
                $totalCommissions,
                $proforma->proforma_number ?? $proforma->id,
                $proformaTotal,
                abs($totalCommissions - $proformaTotal)
            ));
        }
    }

    /**
     * Resolve the Proforma from the TaskExecution's polymorphic target.
     */
    protected function resolveProforma(TaskExecution $execution): Proforma
    {
        // Direct target
        if ($execution->target instanceof Proforma) {
            return $execution->target;
        }

        // Fallback: try loading via target_type / target_id
        if ($execution->target_type === Proforma::class && $execution->target_id) {
            $proforma = Proforma::find($execution->target_id);
            if ($proforma) {
                return $proforma;
            }
        }

        throw new RuntimeException(
            'TaskExecution target is not a Proforma instance. '
            . "Got: {$execution->target_type} (ID: {$execution->target_id})"
        );
    }
}
