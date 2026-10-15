<?php

use App\Support\CommentiSchema;
use Illuminate\Database\Migrations\Migration;

/** Commenti a tutte le tabelle e le colonne di unicofinance (e ai campi del core creati senza commento): vedi README, «Sviluppo». */
return new class extends Migration
{
    private const CREATO = 'Data e ora di creazione del record';

    private const AGGIORNATO = 'Data e ora dell\'ultima modifica del record';

    public function up(): void
    {
        foreach ($this->tabelle() as $tabella => [$commento, $colonne]) {
            CommentiSchema::applica(config('database.default'), $tabella, $commento, $colonne);
        }

        foreach ($this->tabelleCore() as $tabella => [$commento, $colonne]) {
            CommentiSchema::applica(config('unico-core.connection'), $tabella, $commento, $colonne);
        }
    }

    public function down(): void
    {
        // I commenti non si tolgono.
    }

    /** @return array<string, array{0: ?string, 1: array<string, string>}> */
    private function tabelle(): array
    {
        $id = 'Identificativo univoco del record';

        return [
            'blacklist_clienti_employees' => ['Divieto di un istituto verso un dipendente (blacklist)', [
                'id' => $id, 'cliente_id' => 'Istituto (core clientis.id)', 'employee_id' => 'Dipendente non gradito (core employees.id)', 'data_inizio' => 'Inizio del divieto',
                'data_fine' => 'Fine del divieto; vuota se a tempo indeterminato', 'motivo' => 'Motivo del divieto', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'blacklist_clienti_fornitori' => ['Divieto di un istituto verso un produttore (blacklist)', [
                'id' => $id, 'cliente_id' => 'Istituto (core clientis.id)', 'fornitore_id' => 'Produttore non gradito (core fornitoris.id)', 'data_inizio' => 'Inizio del divieto',
                'data_fine' => 'Fine del divieto; vuota se a tempo indeterminato', 'motivo' => 'Motivo del divieto', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'cache' => ['Cache applicativa di Laravel', ['key' => 'Chiave della voce', 'value' => 'Valore serializzato', 'expiration' => 'Scadenza (timestamp UNIX)']],
            'cache_locks' => ['Lock della cache di Laravel', ['key' => 'Chiave del lock', 'owner' => 'Proprietario del lock', 'expiration' => 'Scadenza (timestamp UNIX)']],
            'client_relations' => ['Soci, amministratori, titolari effettivi e referente di una persona giuridica', [
                'notes' => 'Note', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'failed_jobs' => ['Code: lavori falliti', [
                'id' => $id, 'uuid' => 'Identificativo univoco del lavoro', 'connection' => 'Connessione della coda', 'queue' => 'Nome della coda', 'payload' => 'Contenuto del lavoro',
                'exception' => 'Eccezione e stack trace', 'failed_at' => 'Data e ora del fallimento',
            ]],
            'financing_quotes' => [null, [
                'age_at_start' => 'Età del cliente alla decorrenza, in anni', 'birth_date' => 'Data di nascita del cliente', 'employment_relationship' => 'Tipo di rapporto di lavoro (EmploymentRelationship)',
                'sex' => 'Sesso del cliente (M/F)', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'fornitori_listini_provvigionali' => ['Listini provvigionali assegnati ai produttori', [
                'id' => $id, 'listino_provvigionale_id' => 'Listino assegnato', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'job_batches' => ['Code: gruppi di lavori', [
                'id' => $id, 'name' => 'Nome del gruppo', 'total_jobs' => 'Totale dei lavori del gruppo', 'pending_jobs' => 'Lavori ancora da eseguire', 'failed_jobs' => 'Numero di lavori falliti',
                'failed_job_ids' => 'Identificativi dei lavori falliti', 'options' => 'Opzioni serializzate', 'cancelled_at' => 'Annullamento (timestamp UNIX)', 'created_at' => 'Creazione (timestamp UNIX)',
                'finished_at' => 'Fine (timestamp UNIX)',
            ]],
            'jobs' => ['Code: lavori in attesa', [
                'id' => $id, 'queue' => 'Nome della coda', 'payload' => 'Contenuto del lavoro', 'attempts' => 'Tentativi eseguiti', 'reserved_at' => 'Presa in carico (timestamp UNIX)',
                'available_at' => 'Quando diventa eseguibile (timestamp UNIX)', 'created_at' => 'Creazione (timestamp UNIX)',
            ]],
            'kyc_beneficial_owners' => [null, [
                'id' => $id, 'kyc_questionnaire_id' => 'Questionario KYC a cui appartiene', 'position' => 'Ruolo o carica nella società', 'shares_percentage' => 'Percentuale di quote o diritti di voto',
                'control_criterion' => 'Criterio di controllo (quote, voti, altro)', 'pep_status' => 'Condizione di persona politicamente esposta', 'is_verified' => 'Titolare effettivo verificato',
                'declaration_signed_at' => 'Data della dichiarazione firmata', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'kyc_questionnaires' => [null, [
                'id' => $id, 'activity_location' => 'Luogo di svolgimento dell\'attività', 'activity_sector' => 'Settore di attività', 'acts_for_third_party' => 'Il cliente agisce per conto di terzi',
                'compiled_at' => 'Data di compilazione', 'economic_activity' => 'Attività economica svolta', 'executor_link' => 'Legame tra esecutore e cliente', 'executor_pep_status' => 'Condizione PEP dell\'esecutore',
                'financing_nature' => 'Natura del finanziamento', 'financing_purpose' => 'Scopo del finanziamento', 'geographic_area' => 'Area geografica di operatività', 'income_band' => 'Fascia di reddito',
                'legal_nature' => 'Natura giuridica', 'notes' => 'Note', 'pep_status' => 'Condizione PEP del cliente', 'risk_level' => 'Livello di rischio', 'status' => 'Stato della verifica',
                'verified_at' => 'Data di verifica', 'verified_by' => 'Utente che ha verificato', 'wealth_band' => 'Fascia di patrimonio', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'listini_provvigionali' => ['Listini provvigionali per tipo prodotto', [
                'id' => $id, 'is_active' => 'Listino attivo', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'media' => ['File allegati (Spatie Media Library)', [
                'id' => $id, 'model_type' => 'Tipo del record proprietario', 'model_id' => 'Identificativo del record proprietario', 'uuid' => 'Identificativo univoco del file', 'collection_name' => 'Raccolta del file',
                'name' => 'Nome', 'file_name' => 'Nome del file', 'mime_type' => 'Tipo MIME', 'disk' => 'Disco di archiviazione', 'conversions_disk' => 'Disco delle conversioni', 'size' => 'Dimensione in byte',
                'manipulations' => 'Manipolazioni (JSON)', 'custom_properties' => 'Proprietà personalizzate (JSON)', 'generated_conversions' => 'Conversioni generate (JSON)', 'responsive_images' => 'Immagini responsive (JSON)',
                'order_column' => 'Ordine nella raccolta', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'migrations' => ['Migrazioni eseguite', ['id' => $id, 'migration' => 'Nome della migrazione', 'batch' => 'Gruppo di esecuzione']],
            'password_reset_tokens' => ['Token di reimpostazione della password', ['email' => 'Email dell\'utente', 'token' => 'Token (cifrato)', 'created_at' => self::CREATO]],
            'sessions' => ['Sessioni utente', [
                'id' => 'Identificativo della sessione', 'user_id' => 'Utente collegato', 'ip_address' => 'Indirizzo IP', 'user_agent' => 'Browser dell\'utente', 'payload' => 'Dati della sessione',
                'last_activity' => 'Ultima attività (timestamp UNIX)',
            ]],
            'users' => ['Utenti dell\'app', [
                'id' => $id, 'name' => 'Nome', 'email' => 'Email di accesso', 'email_verified_at' => 'Data di verifica dell\'email', 'password' => 'Password (cifrata)',
                'remember_token' => 'Token «ricordami»', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
        ];
    }

    /** @return array<string, array{0: ?string, 1: array<string, string>}> */
    private function tabelleCore(): array
    {
        return [
            'api_calls' => [null, [
                'id' => 'Identificativo univoco della chiamata', 'provider' => 'Servizio chiamato (ApiProvider)', 'operation' => 'Operazione richiesta', 'subject_type' => 'Tipo del soggetto interrogato',
                'user_id' => 'Utente che ha eseguito la chiamata', 'status' => 'Esito della chiamata', 'http_status' => 'Codice HTTP della risposta', 'duration_ms' => 'Durata in millisecondi',
                'cost' => 'Costo della chiamata', 'currency' => 'Valuta del costo', 'error' => 'Messaggio di errore', 'created_at' => self::CREATO, 'updated_at' => self::AGGIORNATO,
            ]],
            'fornitoris' => [null, ['sigla' => 'Sigla del produttore nei codici pratica (es. PM, SB); se manca si ricava dalle iniziali del nome']],
            'migrations' => ['Migrazioni eseguite', ['id' => 'Identificativo univoco del record', 'migration' => 'Nome della migrazione', 'batch' => 'Gruppo di esecuzione']],
            'migrations_core' => ['Migrazioni del core eseguite', ['id' => 'Identificativo univoco del record', 'migration' => 'Nome della migrazione', 'batch' => 'Gruppo di esecuzione']],
        ];
    }
};
