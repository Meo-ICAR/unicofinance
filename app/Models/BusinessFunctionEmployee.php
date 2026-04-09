<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessFunctionEmployee extends Pivot
{
    use SoftDeletes;

    /**
     * Il nome della tabella associata al modello.
     *
     * @var string
     */
    protected $table = 'business_function_employee';

    /**
     * Indica se l'ID è auto-incrementante.
     * Table has composite primary key, so set to false.
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
        'employee_id',
        'is_manager',
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
        'is_manager' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
