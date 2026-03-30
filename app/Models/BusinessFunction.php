<?php

namespace App\Models;

use App\Enums\BusinessFunctionType;
use App\Enums\MacroArea;
use App\Enums\OutsourcableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessFunction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'macro_area',
        'name',
        'type',
        'description',
        'outsourcable_status',
        'managed_by_id',
        'mission',
        'responsibility',
    ];

    protected $casts = [
        'macro_area' => MacroArea::class,
        'type' => BusinessFunctionType::class,
        'outsourcable_status' => OutsourcableStatus::class,
    ];

    /**
     * Ritorna la funzione superiore/padre.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class, 'managed_by_id');
    }

    /**
     * Ritorna le funzioni sottoposte.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(BusinessFunction::class, 'managed_by_id');
    }

    /**
     * Ritorna la Company (Tenant) di appartenenza. Se null, è una funzione globale.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Dipendenti assegnati a questa funzione.
     */
    public function employees(): BelongsToMany
    {
        return $this
            ->belongsToMany(Employee::class, 'business_function_employee')
            ->withPivot('is_manager')
            ->withTimestamps();
    }

    /**
     * Consulenti/Clienti (esterni) assegnati a questa funzione.
     */
    public function clients(): BelongsToMany
    {
        return $this
            ->belongsToMany(Client::class, 'business_function_client')
            ->withPivot('start_date', 'end_date', 'temporary_reason')
            ->withTimestamps()
            ->orderByPivot('start_date', 'desc');
    }

    /**
     * Processi che avvengono all'interno di questa funzione.
     */
    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }

    /**
     * Processi di cui questa funzione è proprietaria (owner/supervisor).
     */
    public function ownedProcesses(): HasMany
    {
        return $this->hasMany(Process::class, 'owner_function_id');
    }

    /**
     * Ruoli RACI assegnati a questa funzione per vari task.
     */
    public function raciAssignments(): HasMany
    {
        return $this->hasMany(RaciAssignment::class);
    }
}
