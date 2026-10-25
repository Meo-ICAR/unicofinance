<?php

namespace App\Support;

use App\Models\Pratica;
use Illuminate\Support\Collection;
use Unico\Core\Models\PraticaStato;

/**
 * Gli stati che si possono scegliere per una pratica: quelli del suo tipo prodotto che vengono dopo lo stato attuale (per `ordine`),
 * più gli stati di rifiuto (`is_rejected`) e lo stato attuale. Senza stato attuale, tutti quelli del prodotto.
 */
class StatiSuccessivi
{
    /**
     * @return array<int, string> nome per id
     */
    public static function per(?string $prodotto, ?int $statoAttualeId): array
    {
        $query = PraticaStato::query()->orderBy('ordine')->orderBy('name');

        if ($prodotto !== null && PraticaStato::query()->where('tipo_finanziamento', $prodotto)->exists()) {
            $query->where('tipo_finanziamento', $prodotto);
        }

        $attuale = $statoAttualeId === null ? null : PraticaStato::query()->find($statoAttualeId);

        if ($attuale !== null) {
            $query->where(fn ($stato) => $stato->where('ordine', '>', $attuale->ordine)->orWhere('is_rejected', true)->orWhere('id', $attuale->getKey()));
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function perPratica(?Pratica $pratica): array
    {
        return self::per($pratica?->tipoprodotto?->name, $pratica?->pratica_stato_id);
    }

    /**
     * Per una selezione di pratiche: gli stati scelti raggruppati per tipo prodotto.
     *
     * @param  Collection<int, Pratica>  $pratiche
     * @return array<string, array<int, string>>
     */
    public static function perPratiche(Collection $pratiche): array
    {
        return $pratiche
            ->loadMissing('tipoprodotto')
            ->groupBy(fn (Pratica $pratica): string => (string) $pratica->tipoprodotto?->name)
            ->map(fn (Collection $gruppo): array => $gruppo->reduce(fn (array $stati, Pratica $pratica): array => $stati + self::perPratica($pratica), []))
            ->mapWithKeys(fn (array $stati, string $prodotto): array => [($prodotto === '' ? 'Senza prodotto' : $prodotto) => $stati])
            ->all();
    }

    /** Lo stato si può dare alla pratica solo se è del suo tipo prodotto (gli stati sono per tipo di finanziamento). */
    public static function adatto(Pratica $pratica, PraticaStato $stato): bool
    {
        return $stato->tipo_finanziamento === null || $stato->tipo_finanziamento === $pratica->tipoprodotto?->name;
    }
}
