<?php

namespace App\Notifications;

use App\Models\TaskExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Implementiamo ShouldQueue per non bloccare il caricamento della pagina mentre parte l'email
class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TaskExecution $task
    ) {}

    // Definiamo i canali.
    // Se il "notifiable" è un modello del DB (es. un Utente), usiamo email + database (campanellina in Filament).
    // Se è solo un indirizzo email volante (On-Demand), usiamo solo l'email.
    public function via(object $notifiable): array
    {
        return isset($notifiable->id) ? ['mail', 'database'] : ['mail'];
    }

    // IL TESTO DELL'EMAIL
    public function toMail(object $notifiable): MailMessage
    {
        $taskName = $this->task->templateTask->name;
        $processName = $this->task->templateTask->process->name ?? 'Processo';

        return (new MailMessage)
            ->subject("Nuovo Task Assegnato: {$taskName}")
            ->greeting('Ciao!')
            ->line("Ti è stato assegnato un nuovo task operativo per la procedura: **{$processName}**.")
            ->line("Operazione da completare: **{$taskName}**")
            ->action('Vai alla tua Scrivania', url('/admin/task-executions'))
            ->line('Grazie per la collaborazione!');
    }

    // IL TESTO PER LA CAMPANELLINA IN FILAMENT (Database)
    public function toArray(object $notifiable): array
    {
        return [
            // Filament usa questi campi di default per le notifiche native
            'title' => 'Nuovo Task: ' . $this->task->templateTask->name,
            'body' => 'Hai un nuovo task in scadenza il ' . ($this->task->due_date ? $this->task->due_date->format('d/m/Y') : 'N/D'),
            'icon' => 'heroicon-o-clipboard-document-check',
            'iconColor' => 'success',
            'url' => '/admin/task-executions', // Link cliccabile dalla notifica
        ];
    }
}
