<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Ricava data di nascita, sesso e luogo di nascita dal codice fiscale di una persona fisica.
 * Gestisce l'omocodia. I comuni vengono risolti con database/data/comuni-catastali.json
 * (codice catastale => "Comune (SIGLA)"); per i nati all'estero (codice Z...) il dataset non
 * contiene gli stati, quindi il luogo e' un segnaposto "Estero (Z123)".
 */
class CodiceFiscaleDecoder
{
    private const MONTHS = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'H' => 6, 'L' => 7, 'M' => 8, 'P' => 9, 'R' => 10, 'S' => 11, 'T' => 12];

    private const OMOCODIA = ['L' => 0, 'M' => 1, 'N' => 2, 'P' => 3, 'Q' => 4, 'R' => 5, 'S' => 6, 'T' => 7, 'U' => 8, 'V' => 9];

    private const PATTERN = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';

    /**
     * @var array<string, string>|null
     */
    private static ?array $municipalities = null;

    private const ODD_VALUES = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23];

    /**
     * Verifica il carattere di controllo (16° carattere) del codice fiscale.
     */
    public function hasValidChecksum(?string $code): bool
    {
        $code = strtoupper(trim((string) $code));

        if (! preg_match(self::PATTERN, $code)) {
            return false;
        }

        $sum = 0;

        foreach (str_split(substr($code, 0, 15)) as $index => $char) {
            $value = ctype_digit($char) ? (int) $char : ord($char) - ord('A');

            $sum += $index % 2 === 0 ? self::ODD_VALUES[$value] : $value;
        }

        return chr(ord('A') + $sum % 26) === $code[15];
    }

    /**
     * Come decode(), ma restituisce null anche se il carattere di controllo non e' corretto.
     *
     * @return array{birth_date: CarbonImmutable, sex: string, birth_place: string}|null
     */
    public function decodeVerified(?string $code): ?array
    {
        return $this->hasValidChecksum($code) ? $this->decode($code) : null;
    }

    /**
     * @return array{birth_date: CarbonImmutable, sex: string, birth_place: string}|null null se il codice non e' valido
     */
    public function decode(?string $code): ?array
    {
        $code = strtoupper(trim((string) $code));

        if (! preg_match(self::PATTERN, $code)) {
            return null;
        }

        $year = (int) $this->digits(substr($code, 6, 2));
        $month = self::MONTHS[$code[8]];
        $day = (int) $this->digits(substr($code, 9, 2));
        $sex = $day > 40 ? 'F' : 'M';
        $day = $day > 40 ? $day - 40 : $day;

        $date = $this->birthDate($year, $month, $day);

        if ($date === null) {
            return null;
        }

        $place = strtoupper(substr($code, 11, 1)).$this->digits(substr($code, 12, 3));

        return ['birth_date' => $date, 'sex' => $sex, 'birth_place' => $this->placeName($place)];
    }

    /**
     * Anno a due cifre: si sceglie il secolo che non porta la data nel futuro.
     */
    private function birthDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        foreach ([2000, 1900] as $century) {
            if (! checkdate($month, $day, $century + $year)) {
                continue;
            }

            $date = CarbonImmutable::create($century + $year, $month, $day)->startOfDay();

            if ($date->lessThanOrEqualTo(now())) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Sostituisce le lettere di omocodia con le cifre corrispondenti.
     */
    private function digits(string $value): string
    {
        return strtr($value, array_map('strval', self::OMOCODIA));
    }

    private function placeName(string $cadastralCode): string
    {
        self::$municipalities ??= json_decode(
            (string) file_get_contents(database_path('data/comuni-catastali.json')),
            true,
        ) ?: [];

        if (isset(self::$municipalities[$cadastralCode])) {
            return self::$municipalities[$cadastralCode];
        }

        return $cadastralCode[0] === 'Z'
            ? "Estero ({$cadastralCode})"
            : "Comune non identificato ({$cadastralCode})";
    }
}
