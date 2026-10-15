<?php

namespace App\Support;

use Unico\Core\Models\Pratica;

/** Importo di mediazione chiesto al cliente, pronto da mostrare nelle pratiche. */
class CompensoMediazione
{
    public static function formatta(float|int|string|null $fisso, float|int|string|null $percentuale, float|int|string|null $importo): string
    {
        $compenso = Pratica::mediationFee($fisso, $percentuale, $importo);

        return $compenso === null ? '—' : '€ '.number_format($compenso, 2, ',', '.');
    }
}
