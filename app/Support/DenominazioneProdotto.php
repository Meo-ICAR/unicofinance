<?php

namespace App\Support;

/**
 * La denominazione del prodotto di Mediafacile: «Cessione - BANCA DEL FUCINO - 270 x 120 (QT00029)», cioè tipo prodotto, banca,
 * rata mensile × durata in mesi e, fra parentesi, il codice della pratica. Rata e durata possono mancare («Mutuo - ING BANK - x 360»,
 * «Prestito - COMPASS BANCA S.P.A - x»), la banca anche («CESSIONE - - 237 x 120»).
 */
class DenominazioneProdotto
{
    /**
     * @return array{tipo: ?string, banca: ?string, rata: ?float, nrate: ?int, codice: ?string}
     */
    public static function leggi(?string $denominazione): array
    {
        $vuoto = ['tipo' => null, 'banca' => null, 'rata' => null, 'nrate' => null, 'codice' => null];
        $testo = trim((string) $denominazione);

        if ($testo === '') {
            return $vuoto;
        }

        $codice = null;

        if (preg_match('/\(([^()]*)\)\s*$/', $testo, $match) === 1) {
            $codice = trim($match[1]) ?: null;
            $testo = trim(substr($testo, 0, -strlen($match[0])));
        }

        $rata = null;
        $nrate = null;

        if (preg_match('/(?:^|\s)-\s*([\d.,]*)\s*[xX×]\s*(\d*)\s*$/u', $testo, $match, PREG_OFFSET_CAPTURE) === 1) {
            $rata = self::importo($match[1][0]);
            $nrate = $match[2][0] === '' ? null : (int) $match[2][0];
            $testo = trim(substr($testo, 0, $match[0][1]));
        }

        $parti = array_map('trim', explode(' - ', rtrim($testo, " -\t"), 2));

        return [
            'tipo' => $parti[0] !== '' ? $parti[0] : null,
            'banca' => isset($parti[1]) && trim($parti[1], " -\t") !== '' ? trim($parti[1], " -\t") : null,
            'rata' => $rata,
            'nrate' => $nrate,
            'codice' => $codice,
        ];
    }

    private static function importo(string $valore): ?float
    {
        $valore = trim($valore);

        if ($valore === '') {
            return null;
        }

        // «1.234,50» (migliaia con il punto, decimali con la virgola) oppure «272,06» o «93.9».
        $valore = str_contains($valore, ',') ? str_replace(',', '.', str_replace('.', '', $valore)) : $valore;

        return is_numeric($valore) && (float) $valore > 0 ? (float) $valore : null;
    }
}
