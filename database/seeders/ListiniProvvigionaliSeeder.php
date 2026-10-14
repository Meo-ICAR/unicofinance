<?php

namespace Database\Seeders;

use App\Models\ListinoProvvigionale;
use Illuminate\Database\Seeder;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

/** Listini provvigionali con percentuale dummy (4), assegnati ad Albanese, Campanella e Fabbri. */
class ListiniProvvigionaliSeeder extends Seeder
{
    private const VALORE_DUMMY = 4;

    /** codice => [nome, tipo prodotto] */
    private const LISTINI = [
        '0017' => ['Cessione Consulente', 'Cessione'],
        '0018' => ['Cessione Special', 'Cessione'],
        '0019' => ['Cessione Premium', 'Cessione'],
        '0020' => ['Mutuo Consulente', 'Mutuo'],
        '0021' => ['Mutuo Special', 'Mutuo'],
        '0023' => ['Prestito Consulente', 'Prestito'],
        '0024' => ['Prestito Special', 'Prestito'],
        '0026' => ['Aziendale Consulente', 'Aziendale'],
        '0027' => ['Aziendale Special', 'Aziendale'],
        '0028' => ['Aziendale Premium', 'Aziendale'],
        '0029' => ['TFS', 'TFS'],
    ];

    /** codice fiscale del produttore => codici listino per le proprie pratiche */
    private const ASSEGNAZIONI = [
        'LBNMNL82C07D643K' => ['0018', '0024', '0020', '0026', '0029'],
        'CMPGGD78E07I158X' => ['0019', '0029', '0023', '0027', '0020'],
        'FBBGRL71A24C573U' => ['0017', '0021', '0023', '0028', '0029'],
    ];

    /** Listini per le pratiche dei fornitori coordinati. */
    private const LISTINI_COORDINATORE = [
        '0029' => ['TFS', 'TFS'],
    ];

    /** codice fiscale del produttore => codici listino per le pratiche dei fornitori che coordina */
    private const ASSEGNAZIONI_COORDINATORE = [
        'CMPGGD78E07I158X' => ['0029'],
    ];

    public function run(): void
    {
        $listini = [];

        foreach ([false => self::LISTINI, true => self::LISTINI_COORDINATORE] as $coordinamento => $definizioni) {
            foreach ($definizioni as $code => [$name, $prodotto]) {
                $tipo = TipoProdotto::where('tipo_prodotto', $prodotto)->firstOrFail();

                $listini[(int) $coordinamento][$code] = ListinoProvvigionale::updateOrCreate(
                    ['code' => $code, 'coordinamento' => $coordinamento],
                    ['name' => $name, 'tipoprodotto_id' => $tipo->id, 'value' => self::VALORE_DUMMY, 'is_active' => true],
                );
            }
        }

        foreach ([false => self::ASSEGNAZIONI, true => self::ASSEGNAZIONI_COORDINATORE] as $coordinamento => $assegnazioni) {
            foreach ($assegnazioni as $nome => $codes) {
                $fornitore = Fornitore::where('cf', $nome)->firstOrFail();

                foreach ($codes as $code) {
                    $listini[(int) $coordinamento][$code]->fornitori()->syncWithoutDetaching([$fornitore->id]);
                }
            }
        }
    }
}
