<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Unico\Core\Models\Fornitore;

/** Compenso della pratica, letto dal database di Proforma. */
class Provvigione extends Model
{
    use SoftDeletes;

    protected $connection = 'proforma';

    protected $table = 'provvigioni';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /** Compenso in uscita verso l'agente (per partita IVA o nome), senza quelli annullati o sospesi. */
    public function scopeDellAgente(Builder $query, Fornitore $agente): Builder
    {
        return $query
            ->where('entrata_uscita', 'Uscita')
            ->where(fn (Builder $q) => $q->where('annullato', false)->orWhereNull('annullato'))
            ->where(fn (Builder $q) => $q->where('status_compenso', '!=', 'Stornata')->orWhereNull('status_compenso'))
            ->where(fn (Builder $q) => $q->where('stato', '!=', 'Sospeso')->orWhereNull('stato'))
            ->where(fn (Builder $q) => $q
                ->when(filled($agente->piva), fn (Builder $w) => $w->where('piva', $agente->piva))
                ->orWhere('denominazione_riferimento', $agente->name));
    }

    /**
     * Esclude le provvigioni di pratiche declinate o sospese. Le pratiche stanno nel database del core e il legame con le provvigioni
     * (database di Proforma) è il codice della pratica: `id_pratica` = `codice_pratica`.
     */
    public function scopeSenzaPraticheDeclinateOSospese(Builder $query): Builder
    {
        $escluse = Pratica::query()
            ->whereIn('codice_pratica', (clone $query)->whereNotNull('id_pratica')->distinct()->pluck('id_pratica'))
            ->where(fn (Builder $q) => $q->whereNotNull('rejected_at')->orWhereHas('praticaStato', fn (Builder $stato) => $stato->where('is_rejected', true)->orWhere('is_suspended', true)))
            ->pluck('codice_pratica');

        return $query->whereNotIn('id_pratica', $escluse);
    }

    /** Pratica del compenso, con il suo stato. */
    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'id_pratica', 'codice_pratica');
    }

    protected function casts(): array
    {
        return [
            'importo' => 'decimal:2',
            'importo_effettivo' => 'decimal:2',
            'percentuale' => 'decimal:4',
            'data_inserimento_compenso' => 'date',
            'data_status' => 'date',
            'data_pagamento' => 'date',
            'erogated_at' => 'date',
            'annullato' => 'boolean',
        ];
    }
}
