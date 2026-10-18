<?php

namespace App\Services;

use App\Models\Pratica;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Unico\Core\Models\PraticaStato;

/**
 * Aggiorna gli stati delle pratiche da un file della banca (CSV o XLSX) con codice pratica banca, nominativo, tipo di prodotto
 * e stato aggiornato. La pratica si trova dal codice banca; il nominativo deve corrispondere al cognome del cliente, altrimenti la
 * riga si salta. Lo stato si cerca per nome (o codice) tra quelli del tipo di finanziamento della pratica.
 */
class ImportAggiornamentiBanca
{
    public function __construct(private CambioStatoPratica $cambioStato) {}

    /**
     * @return array{aggiornate: int, invariate: int, anomalie: list<string>}
     */
    public function importa(string $percorso): array
    {
        $esito = ['aggiornate' => 0, 'invariate' => 0, 'anomalie' => []];

        foreach ($this->righe($percorso) as $numero => $riga) {
            $codice = trim($riga['codice']);
            $stato = trim($riga['stato']);

            if ($codice === '' && $stato === '') {
                continue;
            }

            $pratiche = $codice === '' ? collect() : Pratica::query()->where('codice_pratica_banca', $codice)->get();

            if ($pratiche->count() !== 1) {
                $esito['anomalie'][] = "Riga {$numero}: ".($pratiche->isEmpty() ? "pratica {$codice} non trovata" : "codice {$codice} su più pratiche");

                continue;
            }

            /** @var Pratica $pratica */
            $pratica = $pratiche->first();

            if (! $this->nominativoCorrisponde($pratica, $riga['nominativo'])) {
                $esito['anomalie'][] = "Riga {$numero}: il nominativo «{$riga['nominativo']}» non corrisponde alla pratica {$codice}";

                continue;
            }

            $nuovo = $this->stato($pratica, $stato, $riga['prodotto']);

            if ($nuovo === null) {
                $esito['anomalie'][] = "Riga {$numero}: stato «{$stato}» sconosciuto per la pratica {$codice}";

                continue;
            }

            if ((string) $pratica->pratica_stato_id === (string) $nuovo->getKey()) {
                $esito['invariate']++;

                continue;
            }

            $this->cambioStato->cambia($pratica, $nuovo->getKey(), 'Aggiornamento da file della banca', 'import');
            $esito['aggiornate']++;
        }

        return $esito;
    }

    private function nominativoCorrisponde(Pratica $pratica, string $nominativo): bool
    {
        $cognome = Str::slug((string) $pratica->cognome_cliente);

        return $nominativo === '' || $cognome === '' || str_contains(Str::slug($nominativo), $cognome);
    }

    /** Lo stato con quel nome (o codice): prima quello del tipo di finanziamento della pratica, poi del prodotto indicato dalla banca. */
    private function stato(Pratica $pratica, string $nome, string $prodotto): ?PraticaStato
    {
        $stati = PraticaStato::query()
            ->where(fn ($query) => $query->whereRaw('lower(name) = ?', [Str::lower($nome)])->orWhereRaw('lower(codice) = ?', [Str::lower($nome)]))
            ->orderBy('id')
            ->get();

        foreach ([$pratica->tipoprodotto?->name, $prodotto] as $tipo) {
            $trovato = $tipo === null || $tipo === '' ? null : $stati->first(fn (PraticaStato $stato): bool => strcasecmp((string) $stato->tipo_finanziamento, $tipo) === 0);

            if ($trovato !== null) {
                return $trovato;
            }
        }

        return $stati->count() === 1 ? $stati->first() : null;
    }

    /**
     * @return \Generator<int, array{codice: string, nominativo: string, prodotto: string, stato: string}> indicizzate per numero di riga
     */
    private function righe(string $percorso): \Generator
    {
        $colonne = null;
        $numero = 0;

        foreach ($this->celle($percorso) as $celle) {
            $numero++;

            if ($colonne === null) {
                $colonne = $this->colonne($celle);

                continue;
            }

            yield $numero => array_map(fn (?int $indice): string => $indice === null ? '' : trim((string) ($celle[$indice] ?? '')), $colonne);
        }
    }

    /**
     * @param  list<mixed>  $intestazioni
     * @return array{codice: ?int, nominativo: ?int, prodotto: ?int, stato: ?int}
     */
    private function colonne(array $intestazioni): array
    {
        $nomi = array_map(fn ($cella): string => Str::slug((string) $cella), $intestazioni);
        $trova = function (array $parole) use ($nomi): ?int {
            foreach ($nomi as $indice => $nome) {
                foreach ($parole as $parola) {
                    if (str_contains($nome, $parola)) {
                        return $indice;
                    }
                }
            }

            return null;
        };

        return [
            'codice' => $trova(['codice']),
            'nominativo' => $trova(['nominativo', 'cliente', 'nome']),
            'prodotto' => $trova(['prodotto', 'tipo']),
            'stato' => $trova(['stato']),
        ];
    }

    /** @return \Generator<int, list<mixed>> */
    private function celle(string $percorso): \Generator
    {
        if (strtolower(pathinfo($percorso, PATHINFO_EXTENSION)) === 'xlsx') {
            $lettore = new XlsxReader;
        } else {
            $opzioni = new CsvOptions;
            $intestazione = (string) strtok((string) file_get_contents($percorso, length: 4096), "\n");
            $opzioni->FIELD_DELIMITER = substr_count($intestazione, ';') >= substr_count($intestazione, ',') ? ';' : ',';
            $lettore = new CsvReader($opzioni);
        }

        $lettore->open($percorso);

        try {
            foreach ($lettore->getSheetIterator() as $foglio) {
                foreach ($foglio->getRowIterator() as $riga) {
                    yield array_map(fn ($cella) => $cella instanceof \DateTimeInterface ? $cella->format('Y-m-d') : $cella, $riga->toArray());
                }

                break;
            }
        } finally {
            $lettore->close();
        }
    }
}
