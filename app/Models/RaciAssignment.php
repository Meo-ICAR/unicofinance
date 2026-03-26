<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaciAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'process_task_id',
        'business_function_id',
        'role',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function processTask(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }
}
