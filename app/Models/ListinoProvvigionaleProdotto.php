<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Concerns\UsesCoreConnection;
use Unico\Core\Models\TipoProdotto;

/** Percentuale di un listino su un prodotto. */
class ListinoProvvigionaleProdotto extends Model
{
    use UsesCoreConnection;

    protected $table = 'listini_provvigionali_prodotti';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4'];
    }

    public function listino(): BelongsTo
    {
        return $this->belongsTo(ListinoProvvigionale::class, 'listino_provvigionale_id');
    }

    public function tipoprodotto(): BelongsTo
    {
        return $this->belongsTo(TipoProdotto::class, 'tipoprodotto_id');
    }
}
