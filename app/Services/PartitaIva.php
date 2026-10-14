<?php

namespace App\Services;

class PartitaIva
{
    /**
     * Verifica formato (11 cifre) e cifra di controllo (algoritmo di Luhn) di una partita IVA italiana.
     */
    public function isValid(?string $vat): bool
    {
        $vat = preg_replace('/^IT/i', '', trim((string) $vat));

        if (! preg_match('/^\d{11}$/', $vat) || (int) substr($vat, 0, 7) === 0) {
            return false;
        }

        $sum = 0;

        foreach (str_split($vat) as $index => $digit) {
            $value = (int) $digit;

            if ($index % 2 === 1) {
                $value *= 2;
                $value = $value > 9 ? $value - 9 : $value;
            }

            $sum += $value;
        }

        return $sum % 10 === 0;
    }
}
