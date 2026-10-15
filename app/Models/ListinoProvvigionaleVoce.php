<?php

namespace App\Models;

use App\Support\VociProvvigionali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Percentuale di un listino per una voce di compenso. */
class ListinoProvvigionaleVoce extends Model
{
    /** Database dell'app (non quello del core da cui parte una relazione). */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $table = 'listini_provvigionali_voci';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['percentuale' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        // La percentuale «principale» del listino è quella della prima voce del prodotto.
        $allinea = fn (self $voce) => $voce->listino?->allineaPercentualePrincipale();

        static::saved($allinea);
        static::deleted($allinea);
    }

    public function listino(): BelongsTo
    {
        return $this->belongsTo(ListinoProvvigionale::class, 'listino_provvigionale_id');
    }

    public function etichetta(): string
    {
        return VociProvvigionali::tutte()[$this->voce] ?? $this->voce;
    }
}
