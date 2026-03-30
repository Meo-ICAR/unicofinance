<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDeadline extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_execution_id',
        'sla_policy_id',
        'start_time',
        'warning_at',
        'due_at',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'warning_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * La policy SLA associata.
     */
    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    /**
     * Il task execution associato.
     */
    public function taskExecution(): BelongsTo
    {
        return $this->belongsTo(TaskExecution::class);
    }

    /**
     * Verifica se la deadline è in stato di warning.
     */
    public function isWarning(): bool
    {
        return $this->status === 'warning' || 
               ($this->status === 'active' && now() >= $this->warning_at);
    }

    /**
     * Verifica se la deadline è stata violata (breached).
     */
    public function isBreached(): bool
    {
        return $this->status === 'breached' || 
               ($this->status !== 'completed' && now() >= $this->due_at);
    }

    /**
     * Verifica se la deadline è completata.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' || !is_null($this->completed_at);
    }

    /**
     * Aggiorna lo stato basandosi sulle date attuali.
     */
    public function updateStatus(): void
    {
        if ($this->isCompleted()) {
            $this->status = 'completed';
        } elseif ($this->isBreached()) {
            $this->status = 'breached';
        } elseif ($this->isWarning()) {
            $this->status = 'warning';
        } else {
            $this->status = 'active';
        }
        
        $this->save();
    }

    /**
     * Calcola i minuti rimanenti fino alla scadenza.
     */
    public function getMinutesRemaining(): int
    {
        if ($this->isCompleted()) {
            return 0;
        }
        
        return max(0, now()->diffInMinutes($this->due_at, false));
    }

    /**
     * Calcola la percentuale di tempo utilizzato.
     */
    public function getTimeUsagePercentage(): float
    {
        $totalMinutes = $this->start_time->diffInMinutes($this->due_at);
        $usedMinutes = $this->start_time->diffInMinutes(now());
        
        return min(100, max(0, ($usedMinutes / $totalMinutes) * 100));
    }

    /**
     * Scope per deadline attive.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope per deadline in warning.
     */
    public function scopeWarning($query)
    {
        return $query->where('status', 'warning');
    }

    /**
     * Scope per deadline violate.
     */
    public function scopeBreached($query)
    {
        return $query->where('status', 'breached');
    }

    /**
     * Scope per deadline completate.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope per deadline in scadenza entro X minuti.
     */
    public function scopeDueWithin($query, int $minutes)
    {
        return $query->where('due_at', '<=', now()->addMinutes($minutes))
                    ->where('status', '!=', 'completed');
    }
}
