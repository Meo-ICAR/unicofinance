<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assegnazione di un listino a un produttore (tabella pivot). */
class ListinoProvvigionaleFornitore extends Model
{
    /** Database dell'app: le relazioni dal core altrimenti erediterebbero la connessione core. */
    protected $connection = 'mysql';

    protected $table = 'fornitori_listini_provvigionali';

    protected $guarded = ['id'];

    public function listino(): BelongsTo
    {
        return $this->belongsTo(ListinoProvvigionale::class, 'listino_provvigionale_id');
    }
}
