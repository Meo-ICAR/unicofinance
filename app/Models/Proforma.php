<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

/**
 * Proforma model — target of the "Gestione Proforma ed Emissione Fattura" BPM process.
 *
 * A Proforma represents a preliminary invoice / quote that will eventually
 * be converted into an actual invoice by the accounting department.
 */
class Proforma extends Model
{
    use Userstamps, SoftDeletes;

    protected $guarded = ['id'];

    protected $fillable = [
        'company_id',
        'client_id',
        'employee_id',
        'proforma_number',
        'total_amount',
        'currency',
        'issue_date',
        'due_date',
        'description',
        'status',              // 'draft', 'approved', 'invoiced', 'cancelled'
        'invoice_number',      // populated when the actual invoice is issued
        'invoice_date',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'invoice_date' => 'date',
    ];

    /* ─── Relationships ─── */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The employee who originally created/uploaded this proforma.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * All commission entries associated with this proforma.
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    /**
     * Task executions targeting this proforma.
     */
    public function taskExecutions(): HasMany
    {
        return $this->morphMany(TaskExecution::class, 'target');
    }

    /* ─── Helpers ─── */

    /**
     * Sum of all commission amounts for this proforma.
     */
    public function getTotalCommissionsAttribute(): float
    {
        return (float) $this->commissions()->sum('amount');
    }

    /**
     * Whether this proforma has been converted to an actual invoice.
     */
    public function isInvoiced(): bool
    {
        return $this->status === 'invoiced' && filled($this->invoice_number);
    }
}
