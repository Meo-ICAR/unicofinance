<?php

namespace Database\Seeders;

use App\Models\ListinoProvvigionale;
use App\Models\ListinoProvvigionaleFornitore;
use Illuminate\Database\Seeder;
use Unico\Core\Models\TipoProdotto;

/**
 * Listini della rete distributiva dalla presentazione «Struttura Rete distributiva» (27/06/2025): per ogni prodotto e livello
 * (Consulente del credito, Special, Premium) le percentuali dirette per voce di compenso, e le over provvigionali indirette
 * del Consulente Premium con coordinamento e del Supervisore (listini «come coordinatore»). Il Supervisore, sulle pratiche
 * dirette, ha la tabella del Premium. Ripetibile; i listini non presenti nella presentazione (sede, lead, rete, TFS) non si toccano.
 */
class ListiniReteSeeder extends Seeder
{
    /** Prodotto => [Consulente, Special, Premium] => voce => percentuale (pratiche dirette). */
    private const DIRETTE = [
        'Cessione' => [
            'Consulente' => ['standard' => 60, 'special' => 40],
            'Special' => ['standard' => 80, 'special' => 50],
            'Premium' => ['standard' => 90, 'special' => 60],
        ],
        'Delega' => [
            'Consulente' => ['standard' => 60, 'special' => 40],
            'Special' => ['standard' => 80, 'special' => 50],
            'Premium' => ['standard' => 90, 'special' => 60],
        ],
        'Mutuo' => [
            'Consulente' => ['banca' => 60, 'mediazione' => 58, 'polizza' => 50],
            'Special' => ['banca' => 65, 'mediazione' => 63, 'polizza' => 55],
            'Premium' => ['banca' => 70, 'mediazione' => 67, 'polizza' => 60],
        ],
        'Prestito' => [
            'Consulente' => ['banca' => 50],
            'Special' => ['banca' => 60],
            'Premium' => ['banca' => 65],
        ],
        'Aziendale' => [
            'Consulente' => ['consulenza' => 40],
            'Special' => ['consulenza' => 50],
            'Premium' => ['consulenza' => 60],
        ],
        'Polizza' => [
            'Consulente' => ['premio' => 50, 'brokeraggio' => 65],
            'Special' => ['premio' => 55, 'brokeraggio' => 70],
            'Premium' => ['premio' => 65, 'brokeraggio' => 75],
        ],
    ];

    /** Prodotto => [Coordinamento Premium, Supervisore] => voce => percentuale in più (pratiche dei collaboratori). */
    private const OVER = [
        'Cessione' => [
            'Coordinamento Premium' => ['standard' => 10, 'special' => 5],
            'Supervisore' => ['standard' => 10, 'special' => 5],
        ],
        'Delega' => [
            'Coordinamento Premium' => ['standard' => 10, 'special' => 5],
            'Supervisore' => ['standard' => 10, 'special' => 5],
        ],
        'Mutuo' => [
            'Coordinamento Premium' => ['banca' => 10, 'mediazione' => 5, 'polizza' => 5],
            'Supervisore' => ['banca' => 10, 'mediazione' => 5, 'polizza' => 5],
        ],
        'Prestito' => [
            'Coordinamento Premium' => ['banca' => 5],
            'Supervisore' => ['banca' => 5],
        ],
        'Aziendale' => [
            'Coordinamento Premium' => ['consulenza' => 10],
            'Supervisore' => ['consulenza' => 3],
        ],
        'Polizza' => [
            'Coordinamento Premium' => ['premio' => 5, 'brokeraggio' => 5],
            'Supervisore' => ['premio' => 5, 'brokeraggio' => 5],
        ],
    ];

    /** Codici dei listini già esistenti (report del 14/10/2026): prodotto => livello => codice. */
    private const CODICI_ESISTENTI = [
        'Cessione' => ['Consulente' => '0017', 'Special' => '0018', 'Premium' => '0019'],
        'Mutuo' => ['Consulente' => '0020', 'Special' => '0021', 'Premium' => '0022'],
        'Prestito' => ['Consulente' => '0023', 'Special' => '0024', 'Premium' => '0025'],
        'Aziendale' => ['Consulente' => '0026', 'Special' => '0027', 'Premium' => '0028'],
    ];

    /** Primo codice libero per i listini nuovi. */
    private const PRIMO_CODICE_NUOVO = 34;

    public function run(): void
    {
        $codice = self::PRIMO_CODICE_NUOVO;

        foreach (self::DIRETTE as $prodotto => $livelli) {
            foreach ($livelli as $livello => $voci) {
                $esistente = self::CODICI_ESISTENTI[$prodotto][$livello] ?? null;
                $this->listino($esistente ?? $this->codiceNuovo($prodotto, $livello, $codice), "{$prodotto} {$livello}", $prodotto, false, $voci);
            }
        }

        foreach (self::OVER as $prodotto => $livelli) {
            foreach ($livelli as $livello => $voci) {
                $this->listino($this->codiceNuovo($prodotto, $livello, $codice), "{$prodotto} {$livello}", $prodotto, true, $voci);
            }
        }

        $this->delegaComeCessione();
    }

    /**
     * Cessione, delega e cessione al pensionato (CQP) hanno la stessa tabella provvigionale: chi ha un listino della cessione
     * ha anche quello della delega dello stesso livello (non predefinito). Il CQP usa i listini della cessione.
     */
    private function delegaComeCessione(): void
    {
        foreach (['Consulente', 'Special', 'Premium'] as $livello) {
            $cessione = ListinoProvvigionale::query()->where('name', "Cessione {$livello}")->where('coordinamento', false)->first();
            $delega = ListinoProvvigionale::query()->where('name', "Delega {$livello}")->where('coordinamento', false)->first();

            if ($cessione === null || $delega === null) {
                continue;
            }

            $cessione->assegnazioni()->pluck('fornitori_id')->each(fn ($fornitoreId) => ListinoProvvigionaleFornitore::query()->firstOrCreate(
                ['fornitori_id' => $fornitoreId, 'listino_provvigionale_id' => $delega->getKey()],
                ['predefinito' => false],
            ));
        }
    }

    /** Listini nuovi: il codice dipende da prodotto e livello e non cambia a ogni esecuzione. */
    private function codiceNuovo(string $prodotto, string $livello, int &$contatore): string
    {
        static $assegnati = [];
        $chiave = $prodotto.'|'.$livello;

        if (! isset($assegnati[$chiave])) {
            $esistente = ListinoProvvigionale::query()
                ->where('name', "{$prodotto} {$livello}")
                ->value('code');
            $assegnati[$chiave] = $esistente ?? str_pad((string) $contatore++, 4, '0', STR_PAD_LEFT);
        }

        return $assegnati[$chiave];
    }

    /** @param  array<string, int|float>  $voci */
    private function listino(string $code, string $name, string $prodotto, bool $coordinamento, array $voci): void
    {
        $tipo = TipoProdotto::query()->where('name', $prodotto)->firstOrFail();

        $listino = ListinoProvvigionale::query()->firstOrCreate(
            ['code' => $code, 'coordinamento' => $coordinamento],
            ['name' => $name, 'tipoprodotto_id' => $tipo->getKey(), 'value' => array_values($voci)[0], 'is_active' => true],
        );

        foreach ($voci as $voce => $percentuale) {
            $listino->voci()->updateOrCreate(['voce' => $voce], ['percentuale' => $percentuale]);
        }
    }
}
