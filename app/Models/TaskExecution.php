<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaskExecution extends Model
{
    use LogsActivity, SoftDeletes;
    use Userstamps;

    protected $guarded = ['id'];

    protected $fillable = [
        'process_task_id', 'employee_id', 'client_id',
        'status', 'due_date', 'started_at', 'completed_at', 'previous_task_execution_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Aggiungi questo dentro TaskExecution.php

    protected static function booted()
    {
        static::created(function (TaskExecution $execution) {
            // Quando viene assegnato un task a qualcuno, peschiamo tutte le voci di checklist del template...
            $checklistItems = ChecklistItem::whereHas('checklist', function ($query) use ($execution) {
                $query->where('process_task_id', $execution->process_task_id);
            })->get();

            // ...e creiamo le righe "vuote" (da spuntare) per questa specifica esecuzione
            $dataToInsert = [];
            foreach ($checklistItems as $item) {
                $dataToInsert[] = [
                    'task_execution_id' => $execution->id,
                    'checklist_item_id' => $item->id,
                    'is_checked' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            TaskExecutionChecklistItem::insert($dataToInsert);
        });
    }

    public function templateTask(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'process_task_id');
    }

    public function checkedItems(): HasMany
    {
        return $this->hasMany(TaskExecutionChecklistItem::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Configurazione Audit Trail
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'employee_id', 'audit_dms_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Pratica {$eventName}");
    }

    public function processTask(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class);
    }

    public function executionItems(): HasMany
    {
        return $this->hasMany(TaskExecutionChecklistItem::class);
    }

    /**
     * Le deadline SLA associate a questa esecuzione.
     */
    public function taskDeadline(): HasMany
    {
        return $this->hasMany(TaskDeadline::class);
    }

    /**
     * Verifica se ha una deadline attiva.
     */
    public function hasActiveDeadline(): bool
    {
        return $this->taskDeadline()->active()->exists();
    }

    /**
     * Ottiene la deadline attiva se presente.
     */
    public function getActiveDeadline(): ?TaskDeadline
    {
        return $this->taskDeadline()->active()->first();
    }
}
