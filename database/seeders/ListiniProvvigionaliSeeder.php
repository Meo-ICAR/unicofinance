<?php

namespace Database\Seeders;

use App\Models\ListinoProvvigionale;
use App\Models\ListinoProvvigionaleFornitore;
use Illuminate\Database\Seeder;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

/**
 * Listini provvigionali e loro assegnazione ai collaboratori, dal «Report Listini Collaboratori» del 14/10/2026.
 * Le percentuali sono un valore di prova (4) da sostituire. Ripetibile: le assegnazioni dei collaboratori
 * elencati si riscrivono secondo il report.
 */
class ListiniProvvigionaliSeeder extends Seeder
{
    private const VALORE_DUMMY = 4;

    /** codice => [nome, tipo prodotto] */
    private const LISTINI = [
        '0003' => ['CONSULENTE DI SEDE', null],
        '0016' => ['Cessione Campagna Lead', 'Cessione'],
        '0017' => ['Cessione Consulente', 'Cessione'],
        '0018' => ['Cessione Special', 'Cessione'],
        '0019' => ['Cessione Premium', 'Cessione'],
        '0020' => ['Mutuo Consulente', 'Mutuo'],
        '0021' => ['Mutuo Special', 'Mutuo'],
        '0022' => ['Mutuo Premium', 'Mutuo'],
        '0023' => ['Prestito Consulente', 'Prestito'],
        '0024' => ['Prestito Special', 'Prestito'],
        '0025' => ['Prestito Premium', 'Prestito'],
        '0026' => ['Aziendale Consulente', 'Aziendale'],
        '0027' => ['Aziendale Special', 'Aziendale'],
        '0028' => ['Aziendale Premium', 'Aziendale'],
        '0029' => ['TFS', 'TFS'],
        '0030' => ['Cessione sede', 'Cessione'],
        '0031' => ['Prestito Sede', 'Prestito'],
        '0033' => ['QUINTO RETE', 'Cessione'],
    ];

    /** Listini per le pratiche dei fornitori coordinati. */
    private const LISTINI_COORDINATORE = [
        '0029' => ['TFS', 'TFS'],
    ];

    /**
     * codice del collaboratore => [listino predefinito, altri listini, listini come coordinatore].
     * Il report ripete il predefinito fra gli opzionali: qui compare una volta sola.
     *
     * @var array<string, array{0: string, 1: list<string>, 2?: list<string>}>
     */
    private const ASSEGNAZIONI = [
        'SZ0012' => ['0019', ['0025', '0021', '0026', '0016', '0029']],
        'SZ0015' => ['0019', ['0024', '0020', '0026', '0029']],
        'SZ0019' => ['0033', ['0022', '0023', '0029']],
        'SZ0021' => ['0018', ['0023', '0020', '0026', '0029']],
        'SZ0022' => ['0018', ['0023', '0020', '0026', '0029']],
        'SZ0023' => ['0018', ['0025', '0020', '0026', '0029']],
        'SZ0026' => ['0033', ['0023', '0020', '0026']],
        'SZ0027' => ['0018', ['0025', '0020', '0026', '0016', '0029']],
        'SZ0039' => ['0033', ['0031', '0030', '0029']],
        'SZ0040' => ['0030', ['0031', '0029']],
        'SZ0041' => ['0003', ['0030', '0031', '0029']],
        'SZ0043' => ['0033', []],
        'SZ0065' => ['0019', ['0024', '0020', '0026', '0029']],
        'SZ0079' => ['0019', ['0024', '0021', '0027', '0029', '0016']],
        'SZ0080' => ['0017', ['0020', '0023', '0026']],
        'SZ0083' => ['0030', ['0031']],
        'SZ0085' => ['0030', ['0031', '0029']],
        'SZ0089' => ['0027', ['0019', '0029', '0023', '0020'], ['0029']],
        'SZ0090' => ['0018', ['0024', '0021', '0027', '0029']],
        'SZ0091' => ['0018', ['0023', '0020', '0029', '0026']],
        'SZ0092' => ['0018', ['0026', '0023', '0029', '0020']],
        'SZ0093' => ['0018', ['0026', '0023', '0020', '0029']],
        'SZ0094' => ['0022', ['0017', '0023', '0026', '0029']],
        'SZ0096' => ['0030', ['0031', '0029']],
        'SZ0097' => ['0017', ['0021', '0023', '0028', '0029']],
        'SZ0099' => ['0019', ['0025', '0022', '0027', '0029']],
        'SZ0100' => ['0018', ['0023', '0021', '0027', '0029']],
        'SZ0101' => ['0017', ['0024', '0020', '0028', '0029']],
        'SZ0102' => ['0018', ['0024', '0020', '0026', '0029']],
        'SZ0104' => ['0018', ['0030', '0031']],
        'SZ0105' => ['0033', ['0031']],
    ];

    public function run(): void
    {
        $listini = [];

        foreach ([false => self::LISTINI, true => self::LISTINI_COORDINATORE] as $coordinamento => $definizioni) {
            foreach ($definizioni as $code => [$name, $prodotto]) {
                $tipo = $prodotto === null ? null : TipoProdotto::where('tipo_prodotto', $prodotto)->firstOrFail();

                $listini[(int) $coordinamento][$code] = ListinoProvvigionale::firstOrCreate(
                    ['code' => $code, 'coordinamento' => $coordinamento],
                    ['name' => $name, 'tipoprodotto_id' => $tipo?->id, 'value' => self::VALORE_DUMMY, 'is_active' => true],
                );
            }
        }

        foreach (self::ASSEGNAZIONI as $codice => $assegnazione) {
            [$predefinito, $altri, $comeCoordinatore] = $assegnazione + [2 => []];
            $fornitore = Fornitore::withTrashed()->where('codice', $codice)->firstOrFail();

            ListinoProvvigionaleFornitore::where('fornitori_id', $fornitore->id)->delete();

            foreach ([[$predefinito, false, true], ...array_map(fn (string $code): array => [$code, false, false], $altri), ...array_map(fn (string $code): array => [$code, true, false], $comeCoordinatore)] as [$code, $coordinatore, $default]) {
                ListinoProvvigionaleFornitore::create([
                    'fornitori_id' => $fornitore->id,
                    'listino_provvigionale_id' => $listini[(int) $coordinatore][$code]->id,
                    'predefinito' => $default,
                ]);
            }
        }
    }
}
