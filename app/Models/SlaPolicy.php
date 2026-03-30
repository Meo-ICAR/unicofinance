<?php

namespace App\Models;

use App\Services\SlaService;
use Carbon\Carbon;
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
    public function calculateDueAt(Carbon $startTime): Carbon
    {
        $dueAt = $startTime->copy();

        // Aggiungi i minuti di durata
        $dueAt->addMinutes($this->duration_minutes);

        // Se exclude_weekends è true, escludi weekend
        if ($this->exclude_weekends) {
            $dueAt = $this->excludeWeekends($startTime, $dueAt);
        }

        return $dueAt;
    }

    /**
     * Calcola la data di warning basata sulla data di inizio.
     */
    public function calculateWarningAt(Carbon $startTime): Carbon
    {
        $warningAt = $startTime->copy();

        // Aggiungi i minuti di warning threshold
        $warningAt->addMinutes($this->warning_threshold_minutes);

        // Se exclude_weekends è true, escludi weekend
        if ($this->exclude_weekends) {
            $warningAt = $this->excludeWeekends($startTime, $warningAt);
        }

        return $warningAt;
    }

    /**
     * Escludi weekend dal calcolo delle date.
     */
    private function excludeWeekends(Carbon $start, Carbon $end): Carbon
    {
        $current = $start->copy();
        $weekendDays = 0;

        while ($current <= $end) {
            if ($current->isWeekend()) {  // 6 = Saturday, 7 = Sunday
                $weekendDays++;
            }
            $current->addDay();
        }

        // Aggiungi i weekend giorni alla fine
        $end->addDays($weekendDays);

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

    public function calculateDeadline(Carbon $startDate)
    {
        $service = new SlaService;

        // Calcoliamo la scadenza critica
        $dueAt = $service->calculateBusinessDeadline($startDate, $this->duration_minutes);

        // Il warning lo calcoliamo a ritroso dalla scadenza
        // (Oppure puoi usare lo stesso metodo Business a ritroso)
        $warningAt = $dueAt->copy()->subMinutes($this->warning_threshold_minutes);

        return [
            'due_at' => $dueAt,
            'warning_at' => $warningAt,
        ];
    }
}
