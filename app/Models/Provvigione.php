<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Compenso della pratica, letto dal database di Proforma. */
class Provvigione extends Model
{
    use SoftDeletes;

    protected $connection = 'proforma';

    protected $table = 'provvigioni';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'importo' => 'decimal:2',
            'importo_effettivo' => 'decimal:2',
            'data_inserimento_compenso' => 'date',
            'data_status' => 'date',
            'data_pagamento' => 'date',
            'annullato' => 'boolean',
        ];
    }
}
