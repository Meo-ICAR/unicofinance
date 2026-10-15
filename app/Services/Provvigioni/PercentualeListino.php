<?php

namespace App\Services\Provvigioni;

use App\Models\ListinoProvvigionale;
use App\Models\ListinoProvvigionaleFornitore;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\Pratica;
use Unico\Core\Models\TipoProdotto;

/**
 * Percentuale provvigionale di un produttore, dai listini che gli sono assegnati: per prodotto, diretta o come coordinatore,
 * e per voce di compenso. Tra più listini dello stesso prodotto vale il predefinito, poi il primo per codice.
 */
class PercentualeListino
{
    /**
     * Prodotti che seguono la tabella provvigionale di un altro: i prodotti aziendali (leasing, chirografario, microcredito) hanno
     * quella dell'«Aziendale», ipotecario e mutuo per ristrutturazione quella del «Mutuo». Cessione, delega e CQP hanno già i loro listini.
     *
     * @var array<string, string>
     */
    private const COME = [
        'LEASING' => 'Aziendale',
        'CHIROGRAFARIO' => 'Aziendale',
        'Microcredito' => 'Aziendale',
        'PRESTITO AZIENDALE' => 'Aziendale',
        'IPOTECARIO' => 'Mutuo',
        'Mutuo ristrutturazione' => 'Mutuo',
    ];

    /** @var array<int, int>|null tipoprodotto.id => tipoprodotto.id del listino da usare */
    private ?array $cluster = null;

    /** Listino del produttore per il prodotto (diretto o di coordinamento). */
    public function listino(int|string $fornitoreId, int|string|null $tipoprodottoId, bool $coordinamento = false): ?ListinoProvvigionale
    {
        if ($tipoprodottoId === null) {
            return null;
        }

        $tipoprodottoId = $this->prodottoDelListino((int) $tipoprodottoId);

        $assegnazione = ListinoProvvigionaleFornitore::query()
            ->where('fornitori_id', $fornitoreId)
            ->whereHas('listino', fn ($listino) => $listino->where('tipoprodotto_id', $tipoprodottoId)->where('coordinamento', $coordinamento)->where('is_active', true))
            ->with('listino.voci')
            ->orderByDesc('predefinito')
            ->orderBy('id')
            ->first();

        return $assegnazione?->listino;
    }

    /** Prodotto i cui listini valgono per questo (di solito lo stesso). */
    public function prodottoDelListino(int $tipoprodottoId): int
    {
        if ($this->cluster === null) {
            $id = TipoProdotto::query()->pluck('id', 'name');
            $this->cluster = [];

            foreach (self::COME as $prodotto => $come) {
                if (isset($id[$prodotto], $id[$come])) {
                    $this->cluster[(int) $id[$prodotto]] = (int) $id[$come];
                }
            }
        }

        return $this->cluster[$tipoprodottoId] ?? $tipoprodottoId;
    }

    /**
     * Percentuali del produttore su una pratica, per voce.
     *
     * @return array<string, float>
     */
    public function per(Pratica $pratica, bool $coordinamento = false): array
    {
        $listino = $pratica->fornitori_id === null ? null : $this->listino($pratica->fornitori_id, $pratica->tipoprodotto_id, $coordinamento);

        return $listino === null ? [] : $listino->voci->mapWithKeys(fn ($voce): array => [$voce->voce => (float) $voce->percentuale])->all();
    }

    /**
     * Coppie produttore-prodotto con pratiche e senza un listino diretto assegnato: chi non ha riscontro nei listini.
     * Contano i produttori attivi e le pratiche inserite dalla data indicata: il pregresso non si valorizza.
     *
     * @return Collection<int, array{fornitore_id: int, tipoprodotto_id: int, pratiche: int}>
     */
    public function senzaRiscontro(?CarbonInterface $dal = null): Collection
    {
        $coppie = Pratica::query()
            ->whereNotNull('fornitori_id')->whereNotNull('tipoprodotto_id')->where('is_notowned', false)
            ->when($dal !== null, fn ($query) => $query->where('data_inserimento_pratica', '>=', $dal->toDateString()))
            ->whereIn('fornitori_id', Fornitore::query()->where('is_active', true)->whereNull('dismissed_at')->select('id'))
            ->selectRaw('fornitori_id, tipoprodotto_id, count(*) as pratiche')
            ->groupBy('fornitori_id', 'tipoprodotto_id')
            ->get();

        $coperte = ListinoProvvigionaleFornitore::query()
            ->join('listini_provvigionali as l', 'l.id', '=', 'fornitori_listini_provvigionali.listino_provvigionale_id')
            ->where('l.coordinamento', false)
            ->get(['fornitori_listini_provvigionali.fornitori_id as f', 'l.tipoprodotto_id as t'])
            ->map(fn ($riga): string => $riga->f.'-'.$riga->t)
            ->flip();

        return $coppie
            ->reject(fn ($coppia): bool => isset($coperte[$coppia->fornitori_id.'-'.$this->prodottoDelListino((int) $coppia->tipoprodotto_id)]))
            ->map(fn ($coppia): array => ['fornitore_id' => (int) $coppia->fornitori_id, 'tipoprodotto_id' => (int) $coppia->tipoprodotto_id, 'pratiche' => (int) $coppia->pratiche])
            ->values();
    }
}
