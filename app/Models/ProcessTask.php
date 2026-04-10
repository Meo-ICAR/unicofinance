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

    protected static function booted()
    {
        static::created(function (ProcessTask $task) {
            $process = $task->process;
            $company_id = $process->company_id;
            // Eredita la business function del processo, oppure usa quella assegnata al task come fallback
            $businessFunctionId = $process ? $process->business_function_id : $task->business_function_id;


            $roles = ['R', 'A', 'C', 'I'];
            foreach ($roles as $role) {
                $task->raciAssignments()->firstOrCreate(
                    ['company_id' => $company_id,
                        'process_task_id' => $task->id,
                        'role' => $role], // Controlla se esiste già questo ruolo
                    [

                        'business_function_id' => $businessFunctionId,
                    ]
                );
            }

        });
    }

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

    public function privacyData(): HasMany
    {
        return $this->hasMany(ProcessTaskPrivacyData::class, 'process_task_id');
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
