<?php

return [

    /*
    | Documentazione obbligatoria e cambio di stato. Gli stati elencati richiedono la documentazione completa (check list e
    | fascicolo del prodotto). Finché si recupera lo storico non è bloccante: la pratica passa e si segnala cosa manca.
    */
    'documentazione_bloccante' => (bool) env('ISTRUTTORIA_DOCUMENTAZIONE_BLOCCANTE', false),

    /*
    | Stati in cui il produttore può ancora modificare la pratica. Da «PRESA IN CARICO» in poi (qualunque altro stato, o una presa in
    | carico già avvenuta) la pratica è dell'istruttoria e il produttore la vede soltanto. «SOSPESA» vale solo prima della presa in carico.
    */
    'stati_modificabili_dal_produttore' => ['PREVENTIVO', 'PERFEZIONARE', 'FASCICOLO COMPLETO', 'INVIO IN ISTRUTTORIA', 'INSERITA', 'SOSPESA'],

    'stati_controllati' => ['FASCICOLO COMPLETO', 'INVIO IN ISTRUTTORIA', 'INVIO IN BANCA', 'Caricata Banca'],

    /*
    | Avviso all'istruttoria quando una pratica arriva con il fascicolo firmato. Senza membri della funzione Back Office si usa
    | `email`; in modalità dimostrazione l'avviso parte solo verso `email_demo` (se vuoto non parte).
    */
    'email' => env('ISTRUTTORIA_EMAIL'),

    'email_demo' => env('ISTRUTTORIA_EMAIL_DEMO'),

];
