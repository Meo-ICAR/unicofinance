<?php

return [

    /*
    | Documentazione obbligatoria e cambio di stato. Gli stati elencati richiedono la documentazione completa (check list e
    | fascicolo del prodotto). Finché si recupera lo storico non è bloccante: la pratica passa e si segnala cosa manca.
    */
    'documentazione_bloccante' => (bool) env('ISTRUTTORIA_DOCUMENTAZIONE_BLOCCANTE', false),

    'stati_controllati' => ['FASCICOLO COMPLETO', 'INVIO IN ISTRUTTORIA', 'Caricata Banca'],

];
