<?php

/**
 * API in ingresso per le app che consegnano richieste di finanziamento a unicofinance (oggi unicoagent, l'agente WhatsApp;
 * prima le riceveva unicoloan). Ogni app ha un token e un segreto (php artisan agent-api:client "nome"); le richieste sono firmate.
 */
return [
    // Firma HMAC-SHA256 obbligatoria su ogni richiesta (intestazioni X-Timestamp e X-Signature).
    'require_signature' => (bool) env('AGENT_API_REQUIRE_SIGNATURE', true),

    // Scarto massimo tra l'ora della richiesta e quella del server, in secondi (protegge dal replay).
    'timestamp_tolerance' => (int) env('AGENT_API_TIMESTAMP_TOLERANCE', 300),

    // Prefisso del codice pratica creato da una richiesta (WA-FIN-2026-0001): serve anche a riconoscere i nuovi invii.
    'pratica_prefix' => 'WA-',

    // Stato del prodotto in cui entra la pratica: da perfezionare, come un preventivo che l'agente porta avanti.
    'stato_iniziale' => 'PERFEZIONARE',

    // File dei documenti: dimensione massima (KB) ed estensioni accettate.
    'max_file_kb' => 15360,
    'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'],

    // Codice prodotto della richiesta → nome del tipo prodotto del core (`tipoprodotto.name`). Senza voce si cerca un tipo con la
    // stessa etichetta del prodotto, poi con lo stesso codice, altrimenti si usa il tipo di ripiego.
    'default_tipo_prodotto' => 'Altro',

    'product_map' => [
        // 'quinto' => 'Cessione del quinto',
    ],

    // Codice documento della richiesta → slug (o codice) del tipo documento del core. Senza voce si cerca per slug o codice uguale.
    'document_map' => [
        'documento_identita' => 'carta-identita',
    ],
];
