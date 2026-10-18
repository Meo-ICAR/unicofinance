# Aggiornamento stato dal portale banca

Funzione AI non implementata. Nessun prompt e nessuna lettura periodica del portale.

L'aggiornamento presente nel codice è l'import di un file CSV o XLSX caricato dall'istruttore: `App\Services\ImportAggiornamentiBanca`, azione «Importa aggiornamenti» in `App\Filament\Resources\Praticas\Pages\ListPraticas`. Il cambio stato passa da `App\Services\CambioStatoPratica`.
