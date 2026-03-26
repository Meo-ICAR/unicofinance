<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $fillable = ['process_task_id', 'name', 'description', 'sort_order'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'process_task_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }
}
