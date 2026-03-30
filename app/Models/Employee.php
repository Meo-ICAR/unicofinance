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
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Employee extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;  // Aggiungi LogsActivity se vuoi tracciare le modifiche ai dipendenti

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
            ->withPivot('is_manager')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'role_title', 'email', 'department', 'oam', 'oam_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
