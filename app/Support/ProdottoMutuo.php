<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** I mutui sono i prodotti con un immobile e con cointestatari o garanti. */
class ProdottoMutuo
{
    /** @var array<int, string> */
    public const PRODOTTI = ['Mutuo', 'Mutuo ristrutturazione', 'IPOTECARIO'];

    public static function perPratica(Model $pratica): bool
    {
        return in_array($pratica->tipoprodotto?->name, self::PRODOTTI, true);
    }
}
