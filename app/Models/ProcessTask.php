<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    protected $guarded = ['id'];

    public function executions(): HasMany
    {
        return $this->hasMany(TaskExecution::class);
    }

    /**
     * I tipi di dati personali trattati in questo task.
     */
    public function privacyDataTypes(): BelongsToMany
    {
        return $this
            ->belongsToMany(PrivacyDataType::class, 'process_task_privacy_data')
            ->withPivot('access_level')
            ->withTimestamps()
            ->withUserstamps();
    }

    /**
     * Scope per filtrare task che richiedono accesso in scrittura a dati particolari.
     */
    public function scopeWithSpecialDataWriteAccess($query)
    {
        return $query->whereHas('privacyDataTypes', function ($q) {
            $q
                ->where('category', 'particolari')
                ->where('access_level', 'write');
        });
    }

    /**
     * Scope per filtrare task che richiedono accesso in cancellazione a dati giudiziari.
     */
    public function scopeWithJudicialDataDeleteAccess($query)
    {
        return $query->whereHas('privacyDataTypes', function ($q) {
            $q
                ->where('category', 'giudiziari')
                ->where('access_level', 'delete');
        });
    }
}
