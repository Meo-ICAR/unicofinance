<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registro extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'last_number',
        'n_scheduled',
        'n_progress',
        'n_done',
        'from',
        'to',
        'date',
    ];

    protected $casts = [
        'last_number' => 'integer',
        'n_scheduled' => 'integer',
        'n_progress' => 'integer',
        'n_done' => 'integer',
        'from' => 'integer',
        'to' => 'integer',
        'date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope per ottenere i registri di una specifica azienda
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope per ottenere i registri in un intervallo di date
     */
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * Calcola il totale delle attività (scheduled + progress + done)
     */
    public function getTotalActivitiesAttribute(): int
    {
        return $this->n_scheduled + $this->n_progress + $this->n_done;
    }

    /**
     * Verifica se il registro è completato
     */
    public function isCompleted(): bool
    {
        return $this->n_done >= ($this->to - $this->from + 1);
    }

    /**
     * Calcola la percentuale di completamento
     */
    public function getCompletionPercentageAttribute(): float
    {
        $total = $this->to - $this->from + 1;
        if ($total === 0) {
            return 0;
        }

        return ($this->n_done / $total) * 100;
    }
}
