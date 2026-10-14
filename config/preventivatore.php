<?php

return [

    /*
    | Servizio di preventivazione Mediafacile (specifica WS Offerte).
    | POST con Passkey e parametri nella query; la risposta è XML.
    */
    'url' => env('PREVENTIVATORE_URL'),
    'passkey' => env('PREVENTIVATORE_PASSKEY'),
    'timeout' => (int) env('PREVENTIVATORE_TIMEOUT', 15),

];
