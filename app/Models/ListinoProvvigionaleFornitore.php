<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assegnazione di un listino a un produttore (tabella pivot). */
class ListinoProvvigionaleFornitore extends Model
{
    /** Database dell'app (non quello del core da cui parte una relazione). */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $table = 'fornitori_listini_provvigionali';

    protected $guarded = ['id'];

    public function listino(): BelongsTo
    {
        return $this->belongsTo(ListinoProvvigionale::class, 'listino_provvigionale_id');
    }
}
