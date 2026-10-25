<?php

namespace App\Services;

use App\Models\Pratica;
use App\Support\StatiSinonimi;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\PraticaStato;

/**
 * Aggiorna le pratiche da un file della banca (CSV o XLSX) con almeno il cognome del cedente e lo stato attuale della pratica;
 * facoltativi codice pratica banca e tipo di prodotto. La banca non si chiede: la riconosce dal tracciato già memorizzato su
 * `clientis.tracciato_import` (stesse intestazioni), da una colonna della banca nel file o dalle pratiche che il file riporta
 * (per codice banca o cognome). Al primo import riuscito il tracciato si memorizza sulla banca, così al successivo si parte subito.
 * La pratica si cerca per codice o per cognome tra quelle della banca; il codice banca del file si salva sulla pratica; lo stato si
 * cerca tra quelli del tipo di finanziamento della pratica e, se non c'è, si aggiunge.
 */
class ImportAggiornamentiBanca
{
    public function __construct(private CambioStatoPratica $cambioStato) {}

    /** @var Collection<int, Pratica>|null le pratiche della banca, caricate una sola volta */
    private ?Collection $pratiche = null;

    /**
     * @return array{banca: ?string, tracciato_memorizzato: bool, aggiornate: int, invariate: int, codici: int, stati_aggiunti: list<string>, anomalie: list<string>}
     */
    public function importa(string $percorso): array
    {
        $esito = ['banca' => null, 'tracciato_memorizzato' => false, 'aggiornate' => 0, 'invariate' => 0, 'codici' => 0, 'stati_aggiunti' => [], 'anomalie' => []];
        $this->pratiche = null;

        $celle = iterator_to_array($this->celle($percorso), false);
        $intestazioni = array_map(fn ($cella): string => trim((string) $cella), array_shift($celle) ?? []);
        $firma = $this->firma($intestazioni);

        $banca = $this->bancaDelTracciato($firma);
        $colonne = $banca?->tracciato_import['colonne'] ?? $this->colonne($intestazioni);
        $righe = $this->righe($celle, $colonne);

        if ($banca === null) {
            $banca = $this->bancaDalFile($righe);
        }

        if ($banca === null) {
            $esito['anomalie'][] = 'Banca non riconosciuta: il tracciato non è ancora memorizzato e il file non indica la banca né contiene pratiche già presenti';

            return $esito;
        }

        $esito['banca'] = $banca->name;

        foreach ($righe as $numero => $riga) {
            $this->riga($numero, $riga, (int) $banca->getKey(), $esito);
        }

        if ($esito['aggiornate'] + $esito['invariate'] > 0 && ($banca->tracciato_import['firma'] ?? null) !== $firma) {
            $banca->forceFill(['tracciato_import' => ['firma' => $firma, 'intestazioni' => $intestazioni, 'colonne' => $colonne]])->save();
            $esito['tracciato_memorizzato'] = true;
        }

        return $esito;
    }

    /**
     * @param  array{codice: string, nominativo: string, prodotto: string, stato: string, banca: string}  $riga
     * @param  array{banca: ?string, tracciato_memorizzato: bool, aggiornate: int, invariate: int, codici: int, stati_aggiunti: list<string>, anomalie: list<string>}  $esito
     */
    private function riga(int $numero, array $riga, int $bancaId, array &$esito): void
    {
        $codice = trim($riga['codice']);
        $nominativo = trim($riga['nominativo']);
        $nomeStato = StatiSinonimi::canonico($riga['stato']);

        if ($codice === '' && $nominativo === '' && $nomeStato === '') {
            return;
        }

        if ($nominativo === '' || $nomeStato === '') {
            $esito['anomalie'][] = $nominativo === ''
                ? "Riga {$numero}: cognome mancante, la pratica non si può verificare"
                : "Riga {$numero}: stato mancante per «{$nominativo}»";

            return;
        }

        $pratica = $this->pratica($codice, $nominativo, $riga['prodotto'], $bancaId, $numero, $esito['anomalie']);

        if ($pratica === null) {
            return;
        }

        if ($codice !== '' && (string) $pratica->codice_pratica_banca !== $codice) {
            $pratica->forceFill(['codice_pratica_banca' => $codice])->save();
            $esito['codici']++;
        }

        $nuovo = $this->stato($pratica, $nomeStato, $riga['prodotto'], $esito['stati_aggiunti']);

        if ($nuovo === null) {
            $esito['anomalie'][] = "Riga {$numero}: tipo di prodotto sconosciuto, lo stato «{$nomeStato}» non si può assegnare a «{$nominativo}»";

            return;
        }

        if ((string) $pratica->pratica_stato_id === (string) $nuovo->getKey()) {
            $esito['invariate']++;

            return;
        }

        $this->cambioStato->cambia($pratica, $nuovo->getKey(), 'Aggiornamento da file della banca', 'import');
        $esito['aggiornate']++;
    }

    /** @param  list<string>  $intestazioni */
    private function firma(array $intestazioni): string
    {
        return implode('|', array_map(fn (string $nome): string => Str::slug($nome), $intestazioni));
    }

    /** La banca che ha già memorizzato questo tracciato (stesse intestazioni). */
    private function bancaDelTracciato(string $firma): ?Cliente
    {
        return Cliente::query()->whereNotNull('tracciato_import')->get()->first(fn (Cliente $banca): bool => ($banca->tracciato_import['firma'] ?? null) === $firma);
    }

    /**
     * La banca dal contenuto: la colonna della banca nel file, altrimenti quella a cui appartengono più pratiche già presenti
     * (per codice banca o cognome; una riga che trova pratiche di più banche pesa in parti uguali).
     *
     * @param  array<int, array{codice: string, nominativo: string, prodotto: string, stato: string, banca: string}>  $righe
     */
    private function bancaDalFile(array $righe): ?Cliente
    {
        $nomi = collect($righe)->pluck('banca')->map(fn (string $nome): string => trim($nome))->filter()->unique();

        if ($nomi->isNotEmpty()) {
            $banche = Cliente::query()->get()->filter(fn (Cliente $banca): bool => $nomi->contains(fn (string $nome): bool => Str::slug($nome) === Str::slug((string) $banca->name)));

            if ($banche->count() === 1) {
                return $banche->first();
            }
        }

        $pratiche = Pratica::query()->whereNotNull('clienti_id')->get(['id', 'cognome_cliente', 'clienti_id', 'codice_pratica_banca']);
        $voti = [];

        foreach ($righe as $riga) {
            $codice = trim($riga['codice']);
            $trovate = $codice === '' ? collect() : $pratiche->where('codice_pratica_banca', $codice);

            if ($trovate->isEmpty()) {
                $trovate = $pratiche->filter(fn (Pratica $pratica): bool => $this->nominativoCorrisponde($pratica, trim($riga['nominativo'])));
            }

            $banche = $trovate->pluck('clienti_id')->unique();

            foreach ($banche as $bancaId) {
                $voti[$bancaId] = ($voti[$bancaId] ?? 0) + 1 / $banche->count();
            }
        }

        if ($voti === []) {
            return null;
        }

        arsort($voti);
        $migliori = array_keys($voti, max($voti), true);

        // Se due banche hanno gli stessi voti la banca non è riconosciuta.
        return count($migliori) === 1 ? Cliente::query()->find($migliori[0]) : null;
    }

    /**
     * @param  list<list<mixed>>  $celle
     * @param  array{codice: ?int, nominativo: ?int, prodotto: ?int, stato: ?int, banca: ?int}  $colonne
     * @return array<int, array{codice: string, nominativo: string, prodotto: string, stato: string, banca: string}> indicizzate per numero di riga
     */
    private function righe(array $celle, array $colonne): array
    {
        $righe = [];

        foreach ($celle as $indice => $riga) {
            $righe[$indice + 2] = array_map(fn (?int $colonna): string => $colonna === null ? '' : trim((string) ($riga[$colonna] ?? '')), $colonne);
        }

        return $righe;
    }

    /**
     * La pratica della riga: dal codice banca (con il cognome che deve corrispondere) o, in mancanza, dal cognome tra quelle della banca.
     *
     * @param  list<string>  $anomalie
     */
    private function pratica(string $codice, string $nominativo, string $prodotto, int $bancaId, int $numero, array &$anomalie): ?Pratica
    {
        if ($codice !== '') {
            $perCodice = Pratica::query()->where('codice_pratica_banca', $codice)->where('clienti_id', $bancaId)->get();

            if ($perCodice->count() > 1) {
                $anomalie[] = "Riga {$numero}: codice {$codice} su più pratiche";

                return null;
            }

            if ($perCodice->count() === 1) {
                $trovata = $perCodice->first();

                if (! $this->nominativoCorrisponde($trovata, $nominativo)) {
                    $anomalie[] = "Riga {$numero}: il nominativo «{$nominativo}» non corrisponde alla pratica {$codice}";

                    return null;
                }

                return $trovata;
            }
        }

        $candidate = $this->pratiche($bancaId)->filter(fn (Pratica $pratica): bool => $this->nominativoCorrisponde($pratica, $nominativo));

        if ($prodotto !== '' && ($delProdotto = $candidate->filter(fn (Pratica $pratica): bool => strcasecmp((string) $pratica->tipoprodotto?->name, $prodotto) === 0))->isNotEmpty()) {
            $candidate = $delProdotto;
        }

        if ($candidate->count() > 1 && ($inLavorazione = $candidate->filter(fn (Pratica $pratica): bool => $pratica->praticaStato === null || ($pratica->praticaStato->is_working && ! $pratica->praticaStato->is_suspended)))->isNotEmpty()) {
            $candidate = $inLavorazione;
        }

        if ($codice !== '' && $candidate->count() > 1 && ($senzaAltroCodice = $candidate->filter(fn (Pratica $pratica): bool => blank($pratica->codice_pratica_banca)))->isNotEmpty()) {
            $candidate = $senzaAltroCodice;
        }

        if ($candidate->count() !== 1) {
            $anomalie[] = $candidate->isEmpty()
                ? "Riga {$numero}: nessuna pratica della banca per «{$nominativo}»"
                : "Riga {$numero}: più pratiche della banca per «{$nominativo}» ({$candidate->count()}), indica il codice banca";

            return null;
        }

        return $candidate->first();
    }

    /** @return Collection<int, Pratica> */
    private function pratiche(int $bancaId): Collection
    {
        return $this->pratiche ??= Pratica::query()->where('clienti_id', $bancaId)->with(['tipoprodotto', 'praticaStato'])->get();
    }

    private function nominativoCorrisponde(Pratica $pratica, string $nominativo): bool
    {
        $cognome = Str::slug((string) $pratica->cognome_cliente);

        // Senza nominativo nel file, o senza cognome sulla pratica, il riscontro non si può fare: la riga non cambia lo stato.
        return $nominativo !== '' && $cognome !== '' && str_contains(Str::slug($nominativo), $cognome);
    }

    /**
     * Lo stato con quel nome (o codice) per il tipo di finanziamento della pratica (o, in mancanza, del prodotto indicato dalla banca)
     * o senza tipo, valido per tutti; se non c'è si aggiunge. Senza un tipo di finanziamento si usa lo stato con quel nome, se è uno solo.
     *
     * @param  list<string>  $aggiunti
     */
    private function stato(Pratica $pratica, string $nome, string $prodotto, array &$aggiunti): ?PraticaStato
    {
        $tipo = $pratica->tipoprodotto?->name ?: ($prodotto !== '' ? $prodotto : null);
        $stati = PraticaStato::query()
            ->where(fn ($query) => $query->whereRaw('lower(name) = ?', [Str::lower($nome)])->orWhereRaw('lower(codice) = ?', [Str::lower($nome)]))
            ->orderBy('id')
            ->get();

        if ($tipo === null) {
            return $stati->count() === 1 ? $stati->first() : null;
        }

        $trovato = $stati->first(fn (PraticaStato $stato): bool => strcasecmp((string) $stato->tipo_finanziamento, $tipo) === 0)
            ?? $stati->first(fn (PraticaStato $stato): bool => blank($stato->tipo_finanziamento));

        if ($trovato !== null) {
            return $trovato;
        }

        $aggiunti[] = "{$nome} ({$tipo})";

        return PraticaStato::query()->create([
            'codice' => $nome,
            'name' => $nome,
            'tipo_finanziamento' => $tipo,
            'ordine' => (int) PraticaStato::query()->where('tipo_finanziamento', $tipo)->where('ordine', '<', 500)->max('ordine') + 10,
            'is_working' => true,
            'colore' => 'gray',
        ]);
    }

    /**
     * @param  list<string>  $intestazioni
     * @return array{codice: ?int, nominativo: ?int, prodotto: ?int, stato: ?int, banca: ?int}
     */
    private function colonne(array $intestazioni): array
    {
        $nomi = array_map(fn ($cella): string => Str::slug((string) $cella), $intestazioni);
        $trova = function (array $parole, array $escludi = []) use ($nomi): ?int {
            foreach ($parole as $parola) {
                foreach ($nomi as $indice => $nome) {
                    if (str_contains($nome, $parola) && ! Str::contains($nome, $escludi)) {
                        return $indice;
                    }
                }
            }

            return null;
        };

        return [
            'codice' => $trova(['codice'], ['fiscale']),
            'nominativo' => $trova(['cognome', 'cedente', 'nominativo', 'cliente', 'nome'], ['banca']),
            'prodotto' => $trova(['prodotto', 'tipo']),
            'stato' => $trova(['stato']),
            'banca' => $trova(['banca', 'istituto', 'ente'], ['codice', 'pratica']),
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
