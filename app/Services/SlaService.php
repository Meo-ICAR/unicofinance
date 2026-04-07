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
        // O(1) lookup usando le chiavi array (estremamente più veloce dell'in_array)
        $holidays = array_flip(Holiday::pluck('holiday_date')->toArray());

        while ($minutes > 0) {
            // Se il giorno all'istante attuale è festivo/weekend saltiamo subito a mezzanotte del giorno successivo
            if ($deadline->isWeekend() || isset($holidays[$deadline->toDateString()])) {
                $deadline->addDay()->startOfDay();
                continue;
            }

            // Calcola i minuti rimanenti fino a mezzanotte odierna
            $endOfDay = $deadline->copy()->endOfDay();
            $minutesToMidnight = $deadline->diffInMinutes($endOfDay, false) + 1; // +1 per coprire tutto il gap fino a mezzanotte
            
            if ($minutesToMidnight <= $minutes) {
                // Il task supera la giornata, passa a domani sottraendo i minuti equivalenti all'orario rimasto
                $minutes -= $minutesToMidnight;
                $deadline->addDay()->startOfDay();
            } else {
                // Il tempo si esaurisce completamente all'interno della giornata corrente
                $deadline->addMinutes($minutes);
                $minutes = 0; // Termina il loop
            }
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
