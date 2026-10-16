<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\ProvvigioniRule;
use Unico\Core\Models\TipoProdotto;

/**
 * Provvigioni attive: quanto riconosce la banca a Races per tipo prodotto (`provvigioni_rules` senza agente né livello). Una regola
 * per ogni coppia prodotto-banca con pratiche e una di riferimento per prodotto (senza banca).
 *
 * - Cessione e Delega: libere (percentuale concordata sulla pratica), calcolate sul lordo, cioè rata x numero rate.
 * - Prestiti: quota fissa degli interessi.
 * - Mutuo: percentuale sull'erogato per fasce di LTV.
 * - Altri prodotti: valori realistici di mercato, da confermare con le singole convenzioni.
 *
 * Ripetibile. Le banche si cercano per nome: quelle assenti vengono segnalate e saltate.
 */
class ProvvigioniAttiveSeeder extends Seeder
{
    /** Fasce LTV del mutuo: [da, a, percentuale sull'erogato]. */
    public const FASCE_MUTUO = [[0, 50, 1.60], [50, 70, 1.40], [70, 80, 1.20], [80, 100, 0.90]];

    /** Prodotto => [tipo di calcolo, valore (null = libera), nota]. */
    public const PRODOTTI = [
        'Cessione' => ['lordo', null, 'Libera: percentuale concordata sulla pratica, calcolata sul lordo (rata x numero rate).'],
        'Delega' => ['lordo', null, 'Libera: percentuale concordata sulla pratica, calcolata sul lordo (rata x numero rate).'],
        'Prestito' => ['interessi', 35, 'Fissa, calcolata sugli interessi.'],
        'Prestito INPS' => ['interessi', 35, 'Fissa, calcolata sugli interessi.'],
        'Mutuo' => ['erogato', null, 'Sull\'erogato, per fascia di LTV.'],
        'Aziendale' => ['erogato', 2.00, 'Consulenza: percentuale sull\'erogato.'],
        'PRESTITO AZIENDALE' => ['erogato', 1.80, 'Percentuale sull\'erogato.'],
        'CHIROGRAFARIO' => ['erogato', 1.50, 'Percentuale sull\'erogato.'],
        'IPOTECARIO' => ['erogato', 1.20, 'Percentuale sull\'erogato.'],
        'LEASING' => ['erogato', 1.00, 'Percentuale sul valore finanziato.'],
        'Microcredito' => ['erogato', 3.00, 'Percentuale sull\'erogato.'],
        'TFS' => ['erogato', 1.20, 'Percentuale sull\'anticipo erogato.'],
        'Polizza' => ['premio', 18.00, 'Percentuale sul premio.'],
        'ASSICURAZIONE' => ['premio', 15.00, 'Percentuale sul premio.'],
        'Utenza' => ['fixed', 35.00, 'Importo fisso in euro per contratto attivato.'],
        'Altro' => ['erogato', 1.00, 'Percentuale sull\'erogato.'],
    ];

    /** Prodotto => banche con pratiche. */
    public const BANCHE = [
        'Cessione' => ['IBL BANCA SPA', 'BANCA DEL FUCINO', 'COMPASS BANCA S.P.A.', 'BNL', 'BANCA SISTEMA SPA', 'FINCONTINUO SPA', 'VIVIBANCA SPA', 'BANCA PROGETTO SPA', 'FIGENPA SPA'],
        'Delega' => ['IBL BANCA SPA', 'BANCA DEL FUCINO', 'COMPASS BANCA S.P.A.', 'BNL', 'BANCA SISTEMA SPA'],
        'Prestito' => ['AGOS SPA', 'COMPASS BANCA S.P.A.', 'YOUNITED SA', 'PERMICRO', 'BNL', 'FIGENPA SPA'],
        'Mutuo' => ['CHEBANCA! SPA', 'BNL', 'ING BANK', 'BDM', 'BANCA POPOLARE DI PUGLIA E BASILICATA'],
        'Aziendale' => ['CONFESERFIDI', 'PERMICRO', 'BANCA CF+', 'FIGENPA SPA', 'MK KREDIT ITALY SPA', 'BNL'],
        'CHIROGRAFARIO' => ['CONFESERFIDI', 'BANCA CF+', 'FIGENPA SPA'],
        'Microcredito' => ['FIGENPA SPA', 'MK KREDIT ITALY SPA', 'PERMICRO'],
        'TFS' => ['IBL BANCA SPA', 'BANCA PROGETTO SPA'],
        'Polizza' => ['Iside Broker', 'ASSICURAPOINT', 'VITANUOVA', 'Vitanuova spa'],
        'Utenza' => ['SWITCHO'],
    ];

    /** "Prodotto|banca" => valore diverso da quello di riferimento del prodotto. */
    public const SCOSTAMENTI = [
        'Prestito|AGOS SPA' => 40,
        'Prestito|COMPASS BANCA S.P.A.' => 38,
        'Prestito|YOUNITED SA' => 33,
        'Aziendale|PERMICRO' => 2.50,
        'Microcredito|PERMICRO' => 3.50,
    ];

    public function run(): void
    {
        $banche = Cliente::query()->get()->keyBy(fn (Cliente $banca): string => mb_strtolower(trim((string) $banca->name)));
        $mancanti = [];

        foreach (self::PRODOTTI as $prodotto => [$tipo, $valore, $nota]) {
            $tipoProdotto = TipoProdotto::query()->where('name', $prodotto)->first();

            if ($tipoProdotto === null) {
                $mancanti[] = "prodotto {$prodotto}";

                continue;
            }

            $this->scrivi($tipoProdotto, null, $tipo, $valore, $nota, $prodotto === 'Mutuo');

            foreach (self::BANCHE[$prodotto] ?? [] as $nome) {
                $banca = $banche->get(mb_strtolower($nome));

                if ($banca === null) {
                    $mancanti[] = "banca {$nome}";

                    continue;
                }

                $this->scrivi($tipoProdotto, $banca, $tipo, self::SCOSTAMENTI["{$prodotto}|{$nome}"] ?? $valore, $nota, $prodotto === 'Mutuo');
            }
        }

        if ($mancanti !== []) {
            $this->command?->warn('Non trovati: '.implode(', ', array_unique($mancanti)));
        }
    }

    private function scrivi(TipoProdotto $prodotto, ?Cliente $banca, string $tipo, int|float|null $valore, string $nota, bool $perFasceLtv): void
    {
        foreach ($perFasceLtv ? self::FASCE_MUTUO : [[null, null, $valore]] as [$da, $a, $percentuale]) {
            ProvvigioniRule::query()->updateOrCreate(
                [
                    'tipoprodotto_id' => (string) $prodotto->getKey(),
                    'clienti_id' => $banca === null ? null : (string) $banca->getKey(),
                    'tipoprodotto_sub_id' => null,
                    'kind_id' => null,
                    'fornitori_id' => null,
                    'coordinamento' => false,
                    'iscliente' => false,
                    'ltv_min' => $da,
                    'ltv_max' => $a,
                ],
                [
                    'tipo_provvigioni' => $tipo,
                    'value' => $percentuale,
                    'notes' => $nota.($da === null ? '' : " LTV {$da}-{$a}%."),
                ],
            );
        }
    }
}
