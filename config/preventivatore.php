<?php

return [

    /*
    | Servizio di preventivazione Mediafacile (specifica WS Offerte).
    | POST form con passkey e parametri; la risposta è un XML fmresultset (offerte valide con Errore = 2).
    */
    'url' => env('PREVENTIVATORE_URL'),
    'passkey' => env('PREVENTIVATORE_PASSKEY'),
    'timeout' => (int) env('PREVENTIVATORE_TIMEOUT', 60),

    /*
    | Parametri di account richiesti dal servizio: percentuale di provvigione del collaboratore (0-20),
    | listino e agente.
    */
    'provvigione' => env('PREVENTIVATORE_PROVVIGIONE'),
    'id_listino' => env('PREVENTIVATORE_ID_LISTINO'),
    'id_agente' => env('PREVENTIVATORE_ID_AGENTE'),

    /*
    | Servizio di caricamento lead Mediafacile (specifica 1.5): GET con Passkey e parametri, risposta XML.
    */
    'lead' => [
        'url' => env('LEAD_URL'),
        'passkey' => env('LEAD_PASSKEY'),
        'fonte' => env('LEAD_FONTE', 'unicofinance'),
    ],

];
