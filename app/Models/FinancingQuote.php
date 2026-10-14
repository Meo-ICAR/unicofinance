<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;

/**
 * Preventivo salvato da una simulazione.
 *
 * @property int $number
 * @property int $fornitore_id
 * @property string $status
 * @property int|null $pratica_id
 */
class FinancingQuote extends Model
{
    protected $fillable = [
        'fornitore_id',
        'number',
        'contract_type',
        'sex',
        'birth_date',
        'employment_relationship',
        'company_name',
        'vat_number',
        'hired_at',
        'starts_at',
        'duration_months',
        'assignable_quota',
        'requested_amount',
        'age_at_start',
        'seniority_months',
        'disbursed_min',
        'disbursed_max',
        'status',
        'pratica_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hired_at' => 'date',
            'starts_at' => 'date',
            'duration_months' => 'integer',
            'assignable_quota' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'age_at_start' => 'integer',
            'seniority_months' => 'integer',
            'disbursed_min' => 'decimal:2',
            'disbursed_max' => 'decimal:2',
            'number' => 'integer',
        ];
    }

    public function fornitore(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'fornitore_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'pratica_id');
    }
}
