<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Cognome e nome si riconoscono dal codice fiscale di una persona fisica: le prime tre lettere vengono dal cognome, le tre
 * successive dal nome. Serve all'import da Mediafacile, che spesso riporta i due campi scambiati.
 */
class NominativoDalCodiceFiscale
{
    /**
     * Restituisce cognome e nome nell'ordine giusto: si scambiano solo se il codice fiscale (di 16 caratteri) corrisponde meglio
     * ai campi invertiti; nel dubbio, o se manca uno dei due, restano come sono.
     *
     * @return array{0: ?string, 1: ?string} cognome e nome
     */
    public static function ordina(?string $cognome, ?string $nome, ?string $codiceFiscale): array
    {
        $codice = strtoupper(preg_replace('/\s+/', '', (string) $codiceFiscale));

        if (strlen($codice) !== 16 || blank($cognome) || blank($nome)) {
            return [$cognome, $nome];
        }

        $dritto = (int) (self::codiceCognome($cognome) === substr($codice, 0, 3)) + (int) (self::codiceNome($nome) === substr($codice, 3, 3));
        $scambiato = (int) (self::codiceCognome($nome) === substr($codice, 0, 3)) + (int) (self::codiceNome($cognome) === substr($codice, 3, 3));

        return $scambiato > $dritto ? [$nome, $cognome] : [$cognome, $nome];
    }

    /** Le tre lettere del cognome: consonanti, poi vocali, poi X. */
    public static function codiceCognome(string $cognome): string
    {
        [$consonanti, $vocali] = self::lettere($cognome);

        return substr($consonanti.$vocali.'XXX', 0, 3);
    }

    /** Le tre lettere del nome: con quattro o più consonanti si prendono la prima, la terza e la quarta. */
    public static function codiceNome(string $nome): string
    {
        [$consonanti, $vocali] = self::lettere($nome);

        if (strlen($consonanti) >= 4) {
            return $consonanti[0].$consonanti[2].$consonanti[3];
        }

        return substr($consonanti.$vocali.'XXX', 0, 3);
    }

    /** @return array{0: string, 1: string} consonanti e vocali del testo, in ordine */
    private static function lettere(string $testo): array
    {
        $lettere = preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($testo))) ?? '';

        return [preg_replace('/[AEIOU]/', '', $lettere) ?? '', preg_replace('/[^AEIOU]/', '', $lettere) ?? ''];
    }
}
