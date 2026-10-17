<?php

namespace Tests\Feature;

use App\Support\DenominazioneProdotto;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DenominazioneProdottoTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function denominazioni(): array
    {
        return [
            'cessione' => ['Cessione - BANCA DEL FUCINO - 270 x 120 (QT00029)', ['tipo' => 'Cessione', 'banca' => 'BANCA DEL FUCINO', 'rata' => 270.0, 'nrate' => 120, 'codice' => 'QT00029']],
            'rata con virgola' => ['Prestito - AGOS SPA - 272,06 x 84 (QT01432)', ['tipo' => 'Prestito', 'banca' => 'AGOS SPA', 'rata' => 272.06, 'nrate' => 84, 'codice' => 'QT01432']],
            'rata con punto decimale' => ['Prestito - AGOS SPA - 93.9 x 60 (QT06451)', ['tipo' => 'Prestito', 'banca' => 'AGOS SPA', 'rata' => 93.9, 'nrate' => 60, 'codice' => 'QT06451']],
            'migliaia e decimali' => ['Mutuo - ING BANK - 1.234,50 x 360 (QT00001)', ['tipo' => 'Mutuo', 'banca' => 'ING BANK', 'rata' => 1234.5, 'nrate' => 360, 'codice' => 'QT00001']],
            'banca vuota' => ['CESSIONE - - 237 x 120 (QT04366)', ['tipo' => 'CESSIONE', 'banca' => null, 'rata' => 237.0, 'nrate' => 120, 'codice' => 'QT04366']],
            'senza rata' => ['Mutuo - ING BANK - x 360 (QT01809)', ['tipo' => 'Mutuo', 'banca' => 'ING BANK', 'rata' => null, 'nrate' => 360, 'codice' => 'QT01809']],
            'senza rata né durata' => ['PRESTITO - COMPASS BANCA S.P.A - x (QT05435)', ['tipo' => 'PRESTITO', 'banca' => 'COMPASS BANCA S.P.A', 'rata' => null, 'nrate' => null, 'codice' => 'QT05435']],
            'banca con trattino' => ['Cessione - BANCA - SUD SPA - 150 x 120 (QT00002)', ['tipo' => 'Cessione', 'banca' => 'BANCA - SUD SPA', 'rata' => 150.0, 'nrate' => 120, 'codice' => 'QT00002']],
            'testo libero' => ['CQS', ['tipo' => 'CQS', 'banca' => null, 'rata' => null, 'nrate' => null, 'codice' => null]],
            'vuoto' => ['', ['tipo' => null, 'banca' => null, 'rata' => null, 'nrate' => null, 'codice' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $atteso
     */
    #[DataProvider('denominazioni')]
    public function test_la_denominazione_si_scompone_in_tipo_banca_rata_durata_e_codice(string $denominazione, array $atteso): void
    {
        $this->assertSame($atteso, DenominazioneProdotto::leggi($denominazione));
    }
}
