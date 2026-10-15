<?php

namespace App\Models;

use App\Support\VociProvvigionali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

/**
 * Tabella provvigionale (listino): un prodotto, una percentuale e un ruolo
 * (pratiche dirette o pratiche di fornitori coordinati). Si assegna a più produttori.
 */
class ListinoProvvigionale extends Model
{
    /** Database dell'app: le relazioni dal core altrimenti erediterebbero la connessione core. */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $table = 'listini_provvigionali';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'coordinamento' => 'boolean',
            'value' => 'decimal:4',
        ];
    }

    public function tipoprodotto(): BelongsTo
    {
        return $this->belongsTo(TipoProdotto::class, 'tipoprodotto_id');
    }

    /**
     * Nome della tabella di assegnazione con il database: le query sul fornitore
     * (database core) la raggiungono con una join fra database diversi.
     */
    public static function assegnazioniTable(): string
    {
        return (new static)->getConnection()->getDatabaseName().'.fornitori_listini_provvigionali';
    }

    public function voci(): HasMany
    {
        return $this->hasMany(ListinoProvvigionaleVoce::class, 'listino_provvigionale_id');
    }

    /** Percentuali per voce, nell'ordine del prodotto, es. «Standard 60% · Special 40%». */
    public function riepilogoVoci(): string
    {
        $voci = $this->voci->keyBy('voce');
        $ordine = array_keys(VociProvvigionali::per($this->tipoprodotto?->name) ?: VociProvvigionali::tutte());

        return $voci->sortBy(fn ($voce) => array_search($voce->voce, $ordine, true))
            ->map(fn (ListinoProvvigionaleVoce $voce): string => $voce->etichetta().' '.rtrim(rtrim(number_format((float) $voce->percentuale, 2, ',', ''), '0'), ',').'%')
            ->implode(' · ');
    }

    /** `value` segue la prima voce del prodotto, per gli elenchi che mostrano una sola percentuale. */
    public function allineaPercentualePrincipale(): void
    {
        $ordine = array_keys(VociProvvigionali::per($this->tipoprodotto?->name) ?: VociProvvigionali::tutte());
        $voci = $this->voci()->get()->sortBy(fn ($voce) => array_search($voce->voce, $ordine, true));

        if ($voci->isNotEmpty() && (float) $voci->first()->percentuale !== (float) $this->value) {
            $this->forceFill(['value' => $voci->first()->percentuale])->save();
        }
    }

    public function assegnazioni(): HasMany
    {
        return $this->hasMany(ListinoProvvigionaleFornitore::class, 'listino_provvigionale_id');
    }

    public function fornitori(): BelongsToMany
    {
        return $this->belongsToMany(Fornitore::class, static::assegnazioniTable(), 'listino_provvigionale_id', 'fornitori_id')
            ->withPivot('predefinito')
            ->withTimestamps();
    }
}
