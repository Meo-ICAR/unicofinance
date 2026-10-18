<?php

/*
 * Comandi che unicobpm può lanciare via API (bridge di unico-core: POST /api/commands/{comando}), prima lanciati su unicoloan.
 * Solo quelli elencati, e solo con le opzioni indicate. L'esecuzione è in coda e ha un esito leggibile (GET /api/commands/runs/{id}).
 */
return [
    'pratiche:import-api' => [
        'description' => 'Importa da Mediafacile le pratiche del periodo (di default gli ultimi 60 giorni).',
        'options' => ['start-date', 'end-date'],
    ],
    'provvigioni:import-api' => [
        'description' => 'Importa da Mediafacile i compensi del periodo nelle provvigioni di Proforma (di default gli ultimi 7 giorni).',
        'options' => ['start-date', 'end-date'],
    ],
    'unico-core:documents-schedule-sync' => [
        'description' => 'Ricalcola lo scadenziario documenti.',
        'options' => [],
    ],
    'unico-core:documents-remind' => [
        'description' => 'Invia i solleciti dei documenti in scadenza o mancanti (con force, anche fuori dalle soglie).',
        'options' => ['force'],
    ],
    'signature:sync' => [
        'description' => 'Riallinea le richieste di firma aperte allo stato presso il provider.',
        'options' => ['limit', 'stale-minutes'],
    ],
];
