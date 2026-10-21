<?php

return [

    /*
    | Servizio di preventivazione Mediafacile (specifica WS Offerte).
    | POST con Passkey e parametri nella query; la risposta è XML.
    */
    'url' => env('PREVENTIVATORE_URL'),
    'passkey' => env('PREVENTIVATORE_PASSKEY'),
    'timeout' => (int) env('PREVENTIVATORE_TIMEOUT', 15),

    /*
    | Servizio di caricamento lead Mediafacile (specifica 1.5): GET con Passkey e parametri, risposta XML.
    */
    'lead' => [
        'url' => env('LEAD_URL'),
        'passkey' => env('LEAD_PASSKEY'),
        'fonte' => env('LEAD_FONTE', 'unicofinance'),
    ],

];
