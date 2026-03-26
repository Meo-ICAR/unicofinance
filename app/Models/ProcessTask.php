<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'process_id',
        'business_function_id',
        'sequence_number',
        'name',
        'description',
    ];

    public function checklists(): HasMany
{
    return $this->hasMany(Checklist::class)->orderBy('sort_order');
}
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function raciAssignments(): HasMany
    {
        return $this->hasMany(RaciAssignment::class);
    }
}
