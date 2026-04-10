<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commission model — represents a fee / commission line tied to a Proforma.
 *
 * Multiple commissions can exist per proforma (e.g. consultancy fee,
 * administrative fee, advisory commission, etc.). The BPM action
 * ValidateCommissionsTotalAction checks that their sum matches the
 * proforma's total_amount.
 */
class Commission extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $fillable = [
        'proforma_id',
        'company_id',
        'description',
        'amount',
        'percentage',
        'commission_type',    // e.g. 'advisory', 'administrative', 'performance'
        'due_date',
        'is_paid',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'is_paid' => 'boolean',
    ];

    /* ─── Relationships ─── */

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
