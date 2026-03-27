<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class TaskExecutionChecklistItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'task_execution_id', 'checklist_item_id', 'is_checked', 'checked_at'
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            // ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName}")
            // ->log("Voce esclusa automaticamente: '{$item->description}'. Motivo: Criteri di esenzione soddisfatti (" . class_basename($skipConditionClass) . ").");
            /*
             * ->performedOn($record->taskExecution) // Leghiamo il log alla pratica madre
             *         ->causedBy(auth()->user())            // FONDAMENTALE: L'operatore [R]
             *         ->event('human_validation')
             *         ->withProperties(['ip' => request()->ip()]) // Super audit!
             *         ->log("Validazione manuale effettuata: '{$record->description}'.");
             *
             *         ->action(function (TaskExecutionChecklistItem $record, array $data) {
             *     $record->update([
             *         'requires_revision' => true,
             *         'rejection_reason' => $data['motivo']
             *     ]);
             *
             *     activity()
             *         ->performedOn($record->taskExecution)
             *         ->causedBy(auth()->user())
             *         ->event('exception_raised')
             *         ->log("Pratica bloccata su: '{$record->description}'. Motivo del rifiuto: '{$data['motivo']}'. Sollecito inviato.");
             *
             *         / Dentro App\Actions\SyncWithSapAction
             * public function handle(TaskExecution $execution)
             * {
             *     // ... tua logica di chiamata API ...
             *
             *     activity()
             *         ->performedOn($execution)
             *         ->event('premium_action_executed')
             *         ->log("Automazione completata: Sincronizzazione dati cliente con sistema SAP avvenuta con successo.");
             * }
             *         // Dentro DocumentObserver o un Event Listener
             * public function handle(DocumentUploaded $event)
             * {
             *     // ... trovi la voce della checklist corrispondente ...
             *     $checklistItem->update(['is_checked' => true, 'automated_by_system' => true]);
             *
             *     activity()
             *         ->performedOn($checklistItem->taskExecution)
             *         ->event('dms_automation')
             *         ->log("Validazione automatica: '{$checklistItem->description}'. Documento acquisito tramite DMS (ID Doc: {$event->document->id}).");
             * }
             */
            ->useLogName('task-execution-checklist-item');
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(TaskExecution::class, 'task_execution_id');
    }

    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
