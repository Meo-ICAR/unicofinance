<?php

namespace App\Models;

use App\Enums\EmployeeType;
use App\Enums\SupervisorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// use Spatie\Activitylog\LogOptions;
// use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, SoftDeletes;  // , LogsActivity  Aggiungi LogsActivity se vuoi tracciare le modifiche ai dipendenti

    protected $guarded = ['id'];

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'role_title',
        'cf',
        'email',
        'pec',
        'phone',
        'department',
        'oam',
        'oam_at',
        'oam_name',
        'numero_iscrizione_rui',
        'oam_dismissed_at',
        'ivass',
        'hiring_date',
        'termination_date',
        'company_branch_id',
        'coordinated_by_id',
        'employee_types',
        'supervisor_type',
        'privacy_role',
        'purpose',
        'data_subjects',
        'data_categories',
        'retention_period',
        'extra_eu_transfer',
        'security_measures',
        'privacy_data',
        'is_structure',
        'is_ghost',
    ];

    protected $casts = [
        'employee_types' => EmployeeType::class,
        'supervisor_type' => SupervisorType::class,
        'oam_at' => 'date',
        'oam_dismissed_at' => 'date',
        'hiring_date' => 'date',
        'termination_date' => 'date',
        'is_structure' => 'boolean',
        'is_ghost' => 'boolean',
        'cf' => 'encrypted',
        'email' => 'encrypted',
        'pec' => 'encrypted',
        'phone' => 'encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Assumendo che Employee sia collegato all'utente di login

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'company_branch_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'coordinated_by_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'coordinated_by_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(TaskExecution::class, 'employee_id');
    }

    public function validatedItems(): HasMany
    {
        return $this->hasMany(TaskExecutionChecklistItem::class, 'validated_by_employee_id');
    }

    /**
     * Funzioni aziendali a cui è assegnato il dipendente.
     */
    public function businessFunctions(): BelongsToMany
    {
        return $this
            ->belongsToMany(BusinessFunction::class, 'business_function_employee')
            ->using(BusinessFunctionEmployee::class)
            ->withPivot('is_manager', 'start_date', 'end_date', 'temporary_reason')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'role_title', 'email', 'department', 'oam', 'oam_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function taskExecutions()
    {
        return $this->hasMany(TaskExecution::class);
    }

    public function getPrivacySummaryAttribute()
{
    $privacyRecords = collect();

    // 1. Prendi tutte le funzioni aziendali di questo dipendente
    $functions = $this->businessFunctions()->with([
        'assignedTasks' => function ($query) {
            // Filtriamo: ci interessano solo i task dove il reparto è R (fa il lavoro) o A (è proprietario)
            $query->whereIn('raci_assignments.raci_role', ['R', 'A']);
        },
        'assignedTasks.privacyDataTypes' // Carichiamo i dati privacy (la tua tabella pivot di ieri)
    ])->get();

    // 2. Estrai i dati
    foreach ($functions as $function) {
        foreach ($function->assignedTasks as $task) {
            foreach ($task->privacyDataTypes as $dataType) {
                $privacyRecords->push([
                    'data_type' => $dataType->name,
                    'raci_role' => $task->pivot->raci_role, // Per mostrare in che veste tratta il dato
                    'access_level' => $dataType->pivot->access_level,
                    'purpose' => $dataType->pivot->purpose,
                ]);
            }
        }
    }

    // Rimuovi duplicati: il dipendente potrebbe fare "R" su due task che trattano lo stesso dato
    return $privacyRecords->unique(function ($item) {
        return $item['data_type'] . $item['purpose'];
    })->values();
}
}
