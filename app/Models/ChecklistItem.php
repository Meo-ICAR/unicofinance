<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $fillable = ['checklist_id', 'instruction', 'is_mandatory', 'sort_order'];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
