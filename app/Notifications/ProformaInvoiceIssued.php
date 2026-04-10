<?php

namespace App\Notifications;

use App\Models\Proforma;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ProformaInvoiceIssued
 *
 * Sent to the employee who originally uploaded a Proforma when the
 * accounting department completes the "emit invoice" BPM step.
 */
class ProformaInvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Proforma $proforma,
    ) {
    }

    public function via(object $notifiable): array
    {
        return isset($notifiable->id) ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $proformaId = $this->proforma->proforma_number ?? $this->proforma->id;
        $invoiceNumber = $this->proforma->invoice_number ?? 'N/D';

        return (new MailMessage)
            ->subject("Fattura emessa — Proforma #{$proformaId}")
            ->greeting('Ciao!')
            ->line(
                "La fattura relativa al proforma #{$proformaId} è stata emessa "
                . "dal reparto contabilità."
            )
            ->line("Numero fattura: **{$invoiceNumber}**")
            ->action('Visualizza Proforma', url('/admin/proformas/' . $this->proforma->id))
            ->line('Grazie per la collaborazione!');
    }

    public function toArray(object $notifiable): array
    {
        $proformaId = $this->proforma->proforma_number ?? $this->proforma->id;

        return [
            'title' => "Fattura emessa — Proforma #{$proformaId}",
            'body' => "La fattura relativa al proforma #{$proformaId} è stata emessa dal reparto contabilità.",
            'icon' => 'heroicon-o-document-check',
            'iconColor' => 'success',
            'url' => '/admin/proformas/' . $this->proforma->id,
        ];
    }
}
