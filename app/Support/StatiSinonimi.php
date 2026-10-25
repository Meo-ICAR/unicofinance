<?php

namespace App\Support;

/** Gli stati delle pratiche scritti in una forma diversa da quella del portale (da Mediafacile o dai file delle banche). */
class StatiSinonimi
{
    private const SINONIMI = [
        'INSERITO' => 'INSERITA',
    ];

    /** Il codice dello stato come lo conosce il portale: maiuscolo, spazi normalizzati e sinonimi ricondotti allo stato canonico. */
    public static function canonico(?string $stato): string
    {
        $codice = mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $stato)));

        return self::SINONIMI[$codice] ?? $codice;
    }
}
