<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExecutionChecklistItem extends Model
{
    protected $fillable = [
        'task_execution_id', 'checklist_item_id', 'is_checked', 'checked_at'
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(TaskExecution::class, 'task_execution_id');
    }

    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
