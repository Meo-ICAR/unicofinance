<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessFunctionClient extends Pivot
{
    use SoftDeletes;

    /**
     * Il nome della tabella associata al modello.
     *
     * @var string
     */
    protected $table = 'business_function_client';

    /**
     * Indica se l'ID è auto-incrementante.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Gli attributi che possono essere assegnati massivamente.
     *
     * @var array
     */
    protected $fillable = [
        'business_function_id',
        'client_id',
        'start_date',
        'end_date',
        'temporary_reason',
    ];

    /**
     * Gli attributi che devono essere convertiti in tipi nativi.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
