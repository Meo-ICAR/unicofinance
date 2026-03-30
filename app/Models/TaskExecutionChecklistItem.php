<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mattiverse\Userstamps\Traits\Userstamps;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaskExecutionChecklistItem extends Model
{
    use LogsActivity;
    use Userstamps;

    protected $guarded = ['id'];

    protected $fillable = [
        'task_execution_id', 'checklist_item_id', 'is_checked', 'checked_at',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
            'checked_at' => 'datetime',
            'is_not_applicable' => 'boolean',
            'automated_by_system' => 'boolean',
            'requires_revision' => 'boolean',
        ];
    }

    // Configurazione Audit Trail per il singolo controllo
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_checked', 'is_not_applicable', 'automated_by_system', 'requires_revision', 'rejection_reason', 'validated_by_employee_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()  // Evita log vuoti
            ->useLogName('checklist_audit')  // Un nome per filtrare i log
            ->setDescriptionForEvent(fn (string $eventName) => "Controllo documento/regola {$eventName}");
    }

    public function taskExecution(): BelongsTo
    {
        return $this->belongsTo(TaskExecution::class);
    }

    public function originalChecklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id')->withTrashed();
        // Nota: usiamo withTrashed() perché se il Master Broker elimina la regola, la pratica vecchia deve comunque poter risalire ai dati base.
    }

    public function validatorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by_employee_id');
    }
}
