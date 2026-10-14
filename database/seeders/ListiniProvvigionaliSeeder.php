<?php

namespace Database\Seeders;

use App\Models\ListinoProvvigionale;
use Illuminate\Database\Seeder;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

/** Listini provvigionali con percentuale dummy (4), assegnati ad Albanese e Campanella. */
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

    /** nome produttore => codici listino per le proprie pratiche */
    private const ASSEGNAZIONI = [
        'ALBANESE  Manlio' => ['0018', '0024', '0020', '0026', '0029'],
        'CAMPANELLA GIORGIO AUDENO' => ['0019', '0029', '0023', '0027', '0020'],
        'FABBRI GABRIELE' => ['0017', '0021', '0023', '0028', '0029'],
    ];

    /** nome produttore => codici listino per le pratiche in cui coordina */
    private const ASSEGNAZIONI_COORDINATORE = [
        'CAMPANELLA GIORGIO AUDENO' => ['0029'],
    ];

    public function run(): void
    {
        $listini = [];

        foreach (self::LISTINI as $code => [$name, $prodotto]) {
            $listino = ListinoProvvigionale::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
            $tipo = TipoProdotto::where('tipo_prodotto', $prodotto)->firstOrFail();

            $listino->righe()->firstOrCreate(['tipoprodotto_id' => $tipo->id], ['value' => self::VALORE_DUMMY]);
            $listini[$code] = $listino;
        }

        foreach ([false => self::ASSEGNAZIONI, true => self::ASSEGNAZIONI_COORDINATORE] as $coordinamento => $assegnazioni) {
            foreach ($assegnazioni as $nome => $codes) {
                $fornitore = Fornitore::where('name', $nome)->firstOrFail();

                foreach ($codes as $code) {
                    $esiste = $listini[$code]->fornitori()
                        ->where('fornitoris.id', $fornitore->id)
                        ->wherePivot('coordinamento', $coordinamento)
                        ->exists();

                    if (! $esiste) {
                        $listini[$code]->fornitori()->attach($fornitore->id, ['coordinamento' => $coordinamento]);
                    }
                }
            }
        }
    }
}
