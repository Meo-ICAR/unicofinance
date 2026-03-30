<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'process_type',
        'duration_minutes',
        'warning_threshold_minutes',
        'exclude_weekends',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'warning_threshold_minutes' => 'integer',
        'exclude_weekends' => 'boolean',
    ];

    /**
     * Le deadline associate a questa policy.
     */
    public function taskDeadlines(): HasMany
    {
        return $this->hasMany(TaskDeadline::class);
    }

    /**
     * Calcola la data di scadenza basata sulla data di inizio.
     */
    public function calculateDueAt(\DateTime $startTime): \DateTime
    {
        $dueAt = clone $startTime;
        
        // Aggiungi i minuti di durata
        $dueAt->add(new \DateInterval("PT{$this->duration_minutes}M"));
        
        // Se exclude_weekends è true, escludi weekend
        if ($this->exclude_weekends) {
            $dueAt = $this->excludeWeekends($startTime, $dueAt);
        }
        
        return $dueAt;
    }

    /**
     * Calcola la data di warning basata sulla data di inizio.
     */
    public function calculateWarningAt(\DateTime $startTime): \DateTime
    {
        $warningAt = clone $startTime;
        
        // Aggiungi i minuti di warning threshold
        $warningAt->add(new \DateInterval("PT{$this->warning_threshold_minutes}M"));
        
        // Se exclude_weekends è true, escludi weekend
        if ($this->exclude_weekends) {
            $warningAt = $this->excludeWeekends($startTime, $warningAt);
        }
        
        return $warningAt;
    }

    /**
     * Escludi weekend dal calcolo delle date.
     */
    private function excludeWeekends(\DateTime $start, \DateTime $end): \DateTime
    {
        $current = clone $start;
        $weekendDays = 0;
        
        while ($current <= $end) {
            if ($current->format('N') >= 6) { // 6 = Saturday, 7 = Sunday
                $weekendDays++;
            }
            $current->add(new \DateInterval('P1D'));
        }
        
        // Aggiungi i weekend giorni alla fine
        $end->add(new \DateInterval("P{$weekendDays}D"));
        
        return $end;
    }

    /**
     * Scope per filtrare per tipo di processo.
     */
    public function scopeByProcessType($query, string $processType)
    {
        return $query->where('process_type', $processType);
    }

    /**
     * Scope per policy che escludono weekend.
     */
    public function scopeExcludeWeekends($query)
    {
        return $query->where('exclude_weekends', true);
    }
}
