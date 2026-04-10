<?php

namespace App\Rules\Bpm\Actions;

use App\Contracts\BpmAction;
use App\Models\Employee;
use App\Models\Proforma;
use App\Models\TaskExecution;
use App\Notifications\ProformaInvoiceIssued;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * NotifyProformaUploaderAction
 *
 * When the accounting department completes the "emit invoice" step,
 * this action notifies the employee who originally created/uploaded
 * the Proforma that their invoice has been issued.
 *
 * The notification includes the Proforma ID and the generated invoice number.
 *
 * Registered in checklist_items.action_class as:
 *   App\Rules\Bpm\Actions\NotifyProformaUploaderAction
 */
class NotifyProformaUploaderAction implements BpmAction
{
    public function execute(TaskExecution $execution, array $params = []): void
    {
        $proforma = $this->resolveProforma($execution);

        $uploader = $proforma->uploader;

        if (!$uploader instanceof Employee) {
            Log::warning('NotifyProformaUploaderAction: no uploader (employee) found for Proforma', [
                'proforma_id' => $proforma->id,
            ]);

            return; // Not fatal — the notification is best-effort
        }

        if (!$uploader->email) {
            Log::warning('NotifyProformaUploaderAction: uploader has no email address', [
                'employee_id' => $uploader->id,
                'proforma_id' => $proforma->id,
            ]);

            return;
        }

        Notification::send(
            $uploader,
            new ProformaInvoiceIssued($proforma)
        );

        Log::info('NotifyProformaUploaderAction: notification sent', [
            'proforma_id' => $proforma->id,
            'uploader_id' => $uploader->id,
            'uploader_email' => $uploader->email,
        ]);
    }

    /**
     * Resolve the Proforma from the TaskExecution's polymorphic target.
     */
    protected function resolveProforma(TaskExecution $execution): Proforma
    {
        if ($execution->target instanceof Proforma) {
            return $execution->target;
        }

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
