<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;

class SlaService
{
    /**
     * Calcola la data di scadenza saltando weekend e festivi italiani
     */
    public function calculateBusinessDeadline(Carbon $startDate, int $minutes): Carbon
    {
        $deadline = $startDate->copy();
        $holidays = Holiday::pluck('holiday_date')->toArray();

        while ($minutes > 0) {
            $deadline->addMinute();

            // Se è weekend o è un giorno festivo, non scalare i minuti
            if ($deadline->isWeekend() || in_array($deadline->toDateString(), $holidays)) {
                continue;
            }

            $minutes--;
        }

        return $deadline;
    }

    /**
     * Genera le festività nazionali italiane per un dato anno
     */
    public function getItalianHolidays(int $year): array
    {
        $easter = Carbon::parse(date('Y-m-d', easter_date($year)));
        $easterMonday = $easter->copy()->addDay();

        return [
            "$year-01-01",  // Capodanno
            "$year-01-06",  // Epifania
            "$year-04-25",  // Liberazione
            "$year-05-01",  // Lavoro
            "$year-06-02",  // Repubblica
            "$year-08-15",  // Ferragosto
            "$year-11-01",  // Ognissanti
            "$year-12-08",  // Immacolata
            "$year-12-25",  // Natale
            "$year-12-26",  // S. Stefano
            $easterMonday->toDateString(),  // Pasquetta
        ];
    }
}
