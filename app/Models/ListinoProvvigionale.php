<?php

namespace App\Models;

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
    protected $connection = 'mysql';

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
