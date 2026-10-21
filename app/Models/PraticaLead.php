<?php

namespace App\Models;

use Database\Factories\PraticaLeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Esito di una trasmissione del lead a Mediafacile: l'ultima con `is_ok` dice se la pratica è già stata trasmessa. */
class PraticaLead extends Model
{
    /** @use HasFactory<PraticaLeadFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_ok' => 'boolean'];
    }
}
