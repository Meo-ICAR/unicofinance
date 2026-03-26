<?php

namespace App\Models;

use App\Enums\MacroArea;
use App\Enums\BusinessFunctionType;
use App\Enums\OutsourcableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
