<?php

use App\Models\Client;
use App\Models\Pratica;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Fornitore;

/**
 * Integrazione con unicobpm (prima puntava a unicoloan, dismesso).
 */
return [
    /*
     * Modelli che unicobpm può leggere e scrivere con le API generiche (/api/models/{modello}/fields e PATCH /api/models/{modello}/{id}):
     * alias del modello => classe. Sono gli stessi alias dei processi di unicobpm (`target_model`).
     */
    'models' => [
        'pratica' => Pratica::class,
        'client' => Client::class,
        'cliente' => Cliente::class,
        'fornitore' => Fornitore::class,
        'employee' => Employee::class,
    ],

    /*
     * Campi che la scrittura generica non tocca: lo stato della pratica cambia solo da CambioStatoPratica (storico e date).
     */
    'campi_non_scrivibili' => [
        '*' => ['id', 'created_at', 'updated_at', 'deleted_at', 'company_id'],
        'pratica' => ['pratica_stato_id', 'sended_at', 'approved_at', 'erogated_at', 'rejected_at'],
    ],
];
