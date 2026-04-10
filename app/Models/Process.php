<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Process extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'business_function_id',
        'owner_function_id',
        'process_macro_category_id',
        'name',
        'description',
        'target_model',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function ownerFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class, 'owner_function_id');
    }

    public function processMacroCategory(): BelongsTo
    {
        return $this->belongsTo(ProcessMacroCategory::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProcessTask::class)->orderBy('sequence_number');
    }

    /**
     * Scope per filtrare processi che trattano dati particolari.
     */
    public function scopeWithSpecialData($query)
    {
        return $query->whereHas('tasks.privacyDataTypes', function ($q) {
            $q->where('category', 'particolari');
        });
    }

    /**
     * Scope per filtrare processi che trattano dati giudiziari.
     */
    public function scopeWithJudicialData($query)
    {
        return $query->whereHas('tasks.privacyDataTypes', function ($q) {
            $q->where('category', 'giudiziari');
        });
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
