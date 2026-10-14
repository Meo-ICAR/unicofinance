<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Unico\Core\Concerns\UsesCoreConnection;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\TipoProdotto;

/** Tabella provvigionale (listino), legata ai prodotti e assegnata a più produttori. */
class ListinoProvvigionale extends Model
{
    use UsesCoreConnection;

    protected $table = 'listini_provvigionali';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function righe(): HasMany
    {
        return $this->hasMany(ListinoProvvigionaleProdotto::class, 'listino_provvigionale_id');
    }

    public function prodotti(): BelongsToMany
    {
        return $this->belongsToMany(TipoProdotto::class, 'listini_provvigionali_prodotti', 'listino_provvigionale_id', 'tipoprodotto_id')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function fornitori(): BelongsToMany
    {
        return $this->belongsToMany(Fornitore::class, 'fornitori_listini_provvigionali', 'listino_provvigionale_id', 'fornitori_id')
            ->withPivot('coordinamento')
            ->withTimestamps();
    }
}
