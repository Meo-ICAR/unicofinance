<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Unico\Core\Models\BlacklistClienteFornitore as CoreBlacklistClienteFornitore;

/** Divieto di un istituto di lavorare con un produttore. La chiave della tabella è un UUID. */
class BlacklistClienteFornitore extends CoreBlacklistClienteFornitore
{
    use HasUuids;
}
