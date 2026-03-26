<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class TaskExecution extends Model
{
    protected $fillable = [
        'process_task_id', 'employee_id', 'client_id',
        'status', 'due_date', 'started_at', 'completed_at'
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
}
