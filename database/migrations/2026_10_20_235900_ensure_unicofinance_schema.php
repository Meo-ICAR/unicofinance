<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schema completo del database `unicofinance`, rigenerato dal server MySQL.
 *
 * Idempotente: crea solo le tabelle, colonne, indici e chiavi esterne che mancano,
 * quindi si puo' rieseguire su un database gia' popolato senza errori e senza toccare i dati.
 * Le chiavi esterne che i dati storici non rispettano vengono saltate e segnalate nel log.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return null;
    }

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $schema = $db->getDatabaseName();
        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table => $def) {
                $exists = $db->selectOne('select 1 as x from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]);
                if (! $exists) {
                    $db->statement($def['create']);

                    continue;
                }
                foreach ($def['columns'] as $column => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?', [$schema, $table, $column]);
                    if (! $has) {
                        if (stripos($sql, 'AUTO_INCREMENT') !== false && ! $db->selectOne("select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = 'PRIMARY'", [$schema, $table])) {
                            $sql .= ", ADD PRIMARY KEY (`{$column}`)";
                        }
                        $db->statement("ALTER TABLE `{$table}` ADD COLUMN {$sql}");
                    }
                }
                foreach ($def['indexes'] as $index => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?', [$schema, $table, $index]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$index}");
                    }
                }
            }

            foreach ($this->tables() as $table => $def) {
                foreach ($def['foreign'] as $name => $sql) {
                    $has = $db->selectOne("select 1 as x from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'", [$schema, $table, $name]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$name}");
                    }
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Non si elimina nulla: lo schema e i dati sono condivisi.
    }

    private function tryStatement($db, string $sql, string $label): void
    {
        try {
            $db->statement($sql);
        } catch (\Throwable $e) {
            Log::warning("Migration unicofinance: saltato {$label}: ".$e->getMessage());
        }
    }

    /** @return array<string, array{create: string, columns: array<string, string>, indexes: array<string, string>, foreign: array<string, string>}> */
    private function tables(): array
    {
        return [
        'agent_api_clients' => [
            'create' => 'CREATE TABLE `agent_api_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del client, ad es. unicoagent\',
  `token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'SHA-256 del token Bearer\',
  `secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Segreto HMAC, cifrato\',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT \'Ultima richiesta accettata\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_api_clients_name_unique` (`name`),
  UNIQUE KEY `agent_api_clients_token_hash_unique` (`token_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Clienti dell\'\'API in ingresso per agenti (unicoagent): il token si conserva solo come hash, il segreto firma le richieste.\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del client, ad es. unicoagent\' AFTER `id`',
                'token_hash' => '`token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'SHA-256 del token Bearer\' AFTER `name`',
                'secret' => '`secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Segreto HMAC, cifrato\' AFTER `token_hash`',
                'last_used_at' => '`last_used_at` timestamp NULL DEFAULT NULL COMMENT \'Ultima richiesta accettata\' AFTER `secret`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `last_used_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'agent_api_clients_name_unique' => 'UNIQUE KEY `agent_api_clients_name_unique` (`name`)',
                'agent_api_clients_token_hash_unique' => 'UNIQUE KEY `agent_api_clients_token_hash_unique` (`token_hash`)',
            ],
            'foreign' => [
            ],
        ],
        'api_clients' => [
            'create' => 'CREATE TABLE `api_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome dell\'\'app\',
  `pratica_prefix` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Prefisso del codice delle pratiche create dall\'\'app: l\'\'app raggiunge solo quelle\',
  `webhook_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo dell\'\'app a cui si avvisano documenti respinti o richiesti sulle sue pratiche (firmato con il segreto)\',
  `token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'SHA-256 del token Bearer: il token in chiaro si mostra una volta sola\',
  `secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Segreto per la firma HMAC delle richieste (cifrato)\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Se falso le richieste vengono rifiutate\',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT \'Ultima richiesta autenticata\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_clients_name_unique` (`name`),
  UNIQUE KEY `api_clients_token_hash_unique` (`token_hash`),
  UNIQUE KEY `api_clients_pratica_prefix_unique` (`pratica_prefix`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'App esterne autorizzate a consegnare richieste a unicofinance tramite API (es. unicoagent).\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome dell\'\'app\' AFTER `id`',
                'pratica_prefix' => '`pratica_prefix` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Prefisso del codice delle pratiche create dall\'\'app: l\'\'app raggiunge solo quelle\' AFTER `name`',
                'webhook_url' => '`webhook_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo dell\'\'app a cui si avvisano documenti respinti o richiesti sulle sue pratiche (firmato con il segreto)\' AFTER `pratica_prefix`',
                'token_hash' => '`token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'SHA-256 del token Bearer: il token in chiaro si mostra una volta sola\' AFTER `webhook_url`',
                'secret' => '`secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Segreto per la firma HMAC delle richieste (cifrato)\' AFTER `token_hash`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Se falso le richieste vengono rifiutate\' AFTER `secret`',
                'last_used_at' => '`last_used_at` timestamp NULL DEFAULT NULL COMMENT \'Ultima richiesta autenticata\' AFTER `is_active`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `last_used_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'api_clients_name_unique' => 'UNIQUE KEY `api_clients_name_unique` (`name`)',
                'api_clients_token_hash_unique' => 'UNIQUE KEY `api_clients_token_hash_unique` (`token_hash`)',
                'api_clients_pratica_prefix_unique' => 'UNIQUE KEY `api_clients_pratica_prefix_unique` (`pratica_prefix`)',
            ],
            'foreign' => [
            ],
        ],
        'blacklist_clienti_employees' => [
            'create' => 'CREATE TABLE `blacklist_clienti_employees` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del record\',
  `cliente_id` bigint unsigned NOT NULL COMMENT \'Istituto (core clientis.id)\',
  `employee_id` bigint unsigned NOT NULL COMMENT \'Dipendente non gradito (core employees.id)\',
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Motivo del divieto\',
  `data_inizio` date DEFAULT NULL COMMENT \'Inizio del divieto\',
  `data_fine` date DEFAULT NULL COMMENT \'Fine del divieto; vuota se a tempo indeterminato\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  KEY `blacklist_clienti_employees_cliente_id_foreign` (`cliente_id`),
  KEY `blacklist_clienti_employees_employee_id_cliente_id_index` (`employee_id`,`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Divieto di un istituto verso un dipendente (blacklist)\'',
            'columns' => [
                'id' => '`id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del record\' FIRST',
                'cliente_id' => '`cliente_id` bigint unsigned NOT NULL COMMENT \'Istituto (core clientis.id)\' AFTER `id`',
                'employee_id' => '`employee_id` bigint unsigned NOT NULL COMMENT \'Dipendente non gradito (core employees.id)\' AFTER `cliente_id`',
                'motivo' => '`motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Motivo del divieto\' AFTER `employee_id`',
                'data_inizio' => '`data_inizio` date DEFAULT NULL COMMENT \'Inizio del divieto\' AFTER `motivo`',
                'data_fine' => '`data_fine` date DEFAULT NULL COMMENT \'Fine del divieto; vuota se a tempo indeterminato\' AFTER `data_inizio`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `data_fine`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'blacklist_clienti_employees_cliente_id_foreign' => 'KEY `blacklist_clienti_employees_cliente_id_foreign` (`cliente_id`)',
                'blacklist_clienti_employees_employee_id_cliente_id_index' => 'KEY `blacklist_clienti_employees_employee_id_cliente_id_index` (`employee_id`,`cliente_id`)',
            ],
            'foreign' => [
            ],
        ],
        'blacklist_clienti_fornitori' => [
            'create' => 'CREATE TABLE `blacklist_clienti_fornitori` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del record\',
  `cliente_id` bigint unsigned NOT NULL COMMENT \'Istituto (core clientis.id)\',
  `fornitore_id` bigint unsigned NOT NULL COMMENT \'Produttore non gradito (core fornitoris.id)\',
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Motivo del divieto\',
  `data_inizio` date DEFAULT NULL COMMENT \'Inizio del divieto\',
  `data_fine` date DEFAULT NULL COMMENT \'Fine del divieto; vuota se a tempo indeterminato\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  KEY `blacklist_clienti_fornitori_cliente_id_foreign` (`cliente_id`),
  KEY `blacklist_clienti_fornitori_fornitore_id_cliente_id_index` (`fornitore_id`,`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Divieto di un istituto verso un produttore (blacklist)\'',
            'columns' => [
                'id' => '`id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del record\' FIRST',
                'cliente_id' => '`cliente_id` bigint unsigned NOT NULL COMMENT \'Istituto (core clientis.id)\' AFTER `id`',
                'fornitore_id' => '`fornitore_id` bigint unsigned NOT NULL COMMENT \'Produttore non gradito (core fornitoris.id)\' AFTER `cliente_id`',
                'motivo' => '`motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Motivo del divieto\' AFTER `fornitore_id`',
                'data_inizio' => '`data_inizio` date DEFAULT NULL COMMENT \'Inizio del divieto\' AFTER `motivo`',
                'data_fine' => '`data_fine` date DEFAULT NULL COMMENT \'Fine del divieto; vuota se a tempo indeterminato\' AFTER `data_inizio`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `data_fine`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'blacklist_clienti_fornitori_cliente_id_foreign' => 'KEY `blacklist_clienti_fornitori_cliente_id_foreign` (`cliente_id`)',
                'blacklist_clienti_fornitori_fornitore_id_cliente_id_index' => 'KEY `blacklist_clienti_fornitori_fornitore_id_cliente_id_index` (`fornitore_id`,`cliente_id`)',
            ],
            'foreign' => [
            ],
        ],
        'cache' => [
            'create' => 'CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Chiave della voce\',
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Valore serializzato\',
  `expiration` bigint NOT NULL COMMENT \'Scadenza (timestamp UNIX)\',
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Cache applicativa di Laravel\'',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Chiave della voce\' FIRST',
                'value' => '`value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Valore serializzato\' AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL COMMENT \'Scadenza (timestamp UNIX)\' AFTER `value`',
            ],
            'indexes' => [
                'cache_expiration_index' => 'KEY `cache_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'cache_locks' => [
            'create' => 'CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Chiave del lock\',
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Proprietario del lock\',
  `expiration` bigint NOT NULL COMMENT \'Scadenza (timestamp UNIX)\',
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Lock della cache di Laravel\'',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Chiave del lock\' FIRST',
                'owner' => '`owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Proprietario del lock\' AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL COMMENT \'Scadenza (timestamp UNIX)\' AFTER `owner`',
            ],
            'indexes' => [
                'cache_locks_expiration_index' => 'KEY `cache_locks_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'chat_messages' => [
            'create' => 'CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID intero autoincrementante\',
  `company_id` bigint unsigned DEFAULT NULL COMMENT \'Azienda (tenant) proprietaria del record\',
  `user_id` bigint unsigned DEFAULT NULL COMMENT \'Utente collegato al record\',
  `thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Conversazione a cui appartiene il messaggio\',
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Autore del messaggio (user, assistant)\',
  `content` json DEFAULT NULL COMMENT \'Testo del messaggio\',
  `meta` json DEFAULT NULL COMMENT \'Dati aggiuntivi del messaggio (JSON)\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  KEY `chat_messages_thread_id_id_index` (`thread_id`,`id`),
  KEY `chat_messages_thread_id_index` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Messaggi delle conversazioni con l\'\'assistente AI (NeuronAI), sul database di unicofinance\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID intero autoincrementante\' FIRST',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL COMMENT \'Azienda (tenant) proprietaria del record\' AFTER `id`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL COMMENT \'Utente collegato al record\' AFTER `company_id`',
                'thread_id' => '`thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Conversazione a cui appartiene il messaggio\' AFTER `user_id`',
                'role' => '`role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Autore del messaggio (user, assistant)\' AFTER `thread_id`',
                'content' => '`content` json DEFAULT NULL COMMENT \'Testo del messaggio\' AFTER `role`',
                'meta' => '`meta` json DEFAULT NULL COMMENT \'Dati aggiuntivi del messaggio (JSON)\' AFTER `content`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `meta`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'chat_messages_thread_id_id_index' => 'KEY `chat_messages_thread_id_id_index` (`thread_id`,`id`)',
                'chat_messages_thread_id_index' => 'KEY `chat_messages_thread_id_index` (`thread_id`)',
            ],
            'foreign' => [
            ],
        ],
        'client_relations' => [
            'create' => 'CREATE TABLE `client_relations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del legame\',
  `company_id` bigint unsigned NOT NULL COMMENT \'Cliente persona giuridica (core clients.id)\',
  `client_id` bigint unsigned NOT NULL COMMENT \'Soggetto collegato (core clients.id)\',
  `role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'legale_rappresentante | amministratore | socio | titolare_effettivo | garante | altro\',
  `shares_percentage` decimal(5,2) DEFAULT NULL COMMENT \'Percentuale quote possedute\',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_relations_company_id_client_id_role_unique` (`company_id`,`client_id`,`role`),
  KEY `client_relations_company_id_index` (`company_id`),
  KEY `client_relations_client_id_index` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Soci, amministratori, titolari effettivi e referente di una persona giuridica\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del legame\' FIRST',
                'company_id' => '`company_id` bigint unsigned NOT NULL COMMENT \'Cliente persona giuridica (core clients.id)\' AFTER `id`',
                'client_id' => '`client_id` bigint unsigned NOT NULL COMMENT \'Soggetto collegato (core clients.id)\' AFTER `company_id`',
                'role' => '`role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'legale_rappresentante | amministratore | socio | titolare_effettivo | garante | altro\' AFTER `client_id`',
                'shares_percentage' => '`shares_percentage` decimal(5,2) DEFAULT NULL COMMENT \'Percentuale quote possedute\' AFTER `role`',
                'notes' => '`notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note\' AFTER `shares_percentage`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `notes`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'client_relations_company_id_client_id_role_unique' => 'UNIQUE KEY `client_relations_company_id_client_id_role_unique` (`company_id`,`client_id`,`role`)',
                'client_relations_company_id_index' => 'KEY `client_relations_company_id_index` (`company_id`)',
                'client_relations_client_id_index' => 'KEY `client_relations_client_id_index` (`client_id`)',
            ],
            'foreign' => [
            ],
        ],
        'failed_jobs' => [
            'create' => 'CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del lavoro\',
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Connessione della coda\',
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome della coda\',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Contenuto del lavoro\',
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Eccezione e stack trace\',
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'Data e ora del fallimento\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Code: lavori falliti\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'uuid' => '`uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del lavoro\' AFTER `id`',
                'connection' => '`connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Connessione della coda\' AFTER `uuid`',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome della coda\' AFTER `connection`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Contenuto del lavoro\' AFTER `queue`',
                'exception' => '`exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Eccezione e stack trace\' AFTER `payload`',
                'failed_at' => '`failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'Data e ora del fallimento\' AFTER `exception`',
            ],
            'indexes' => [
                'failed_jobs_uuid_unique' => 'UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)',
                'failed_jobs_connection_queue_failed_at_index' => 'KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)',
            ],
            'foreign' => [
            ],
        ],
        'financing_quotes' => [
            'create' => 'CREATE TABLE `financing_quotes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID intero autoincrementante\',
  `fornitore_id` bigint unsigned NOT NULL COMMENT \'Produttore (fornitore sul database core) a cui appartiene il preventivo\',
  `number` int unsigned NOT NULL COMMENT \'Progressivo del preventivo per quel produttore\',
  `contract_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Tipo contratto della simulazione\',
  `sex` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sesso del cliente (M/F)\',
  `birth_date` date DEFAULT NULL COMMENT \'Data di nascita del cliente\',
  `employment_relationship` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo di rapporto di lavoro (EmploymentRelationship)\',
  `company_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Ragione sociale, per i prodotti corporate\',
  `vat_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Partita IVA, per i prodotti corporate\',
  `hired_at` date DEFAULT NULL COMMENT \'Data assunzione, assente per i pensionati\',
  `starts_at` date NOT NULL COMMENT \'Data decorrenza\',
  `duration_months` smallint unsigned NOT NULL COMMENT \'Durata in mesi\',
  `assignable_quota` decimal(12,2) DEFAULT NULL COMMENT \'Quota cedibile, per cessione e delega\',
  `requested_amount` decimal(12,2) DEFAULT NULL COMMENT \'Importo richiesto, per prestito e mutuo\',
  `age_at_start` tinyint unsigned DEFAULT NULL COMMENT \'Età del cliente alla decorrenza, in anni\',
  `seniority_months` smallint unsigned DEFAULT NULL COMMENT \'Anzianità di servizio in mesi alla decorrenza\',
  `disbursed_min` decimal(12,2) DEFAULT NULL COMMENT \'Importo erogato minimo restituito dal preventivatore\',
  `disbursed_max` decimal(12,2) DEFAULT NULL COMMENT \'Importo erogato massimo restituito dal preventivatore\',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'salvato oppure perfezionato\',
  `pratica_id` bigint unsigned DEFAULT NULL COMMENT \'Pratica core creata se il preventivo è stato perfezionato\',
  `user_id` bigint unsigned DEFAULT NULL COMMENT \'Utente core che ha concluso la simulazione\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `financing_quotes_fornitore_id_number_unique` (`fornitore_id`,`number`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Preventivi della simulazione agenti. Il progressivo è per produttore.\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID intero autoincrementante\' FIRST',
                'fornitore_id' => '`fornitore_id` bigint unsigned NOT NULL COMMENT \'Produttore (fornitore sul database core) a cui appartiene il preventivo\' AFTER `id`',
                'number' => '`number` int unsigned NOT NULL COMMENT \'Progressivo del preventivo per quel produttore\' AFTER `fornitore_id`',
                'contract_type' => '`contract_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Tipo contratto della simulazione\' AFTER `number`',
                'sex' => '`sex` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sesso del cliente (M/F)\' AFTER `contract_type`',
                'birth_date' => '`birth_date` date DEFAULT NULL COMMENT \'Data di nascita del cliente\' AFTER `sex`',
                'employment_relationship' => '`employment_relationship` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo di rapporto di lavoro (EmploymentRelationship)\' AFTER `birth_date`',
                'company_name' => '`company_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Ragione sociale, per i prodotti corporate\' AFTER `employment_relationship`',
                'vat_number' => '`vat_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Partita IVA, per i prodotti corporate\' AFTER `company_name`',
                'hired_at' => '`hired_at` date DEFAULT NULL COMMENT \'Data assunzione, assente per i pensionati\' AFTER `vat_number`',
                'starts_at' => '`starts_at` date NOT NULL COMMENT \'Data decorrenza\' AFTER `hired_at`',
                'duration_months' => '`duration_months` smallint unsigned NOT NULL COMMENT \'Durata in mesi\' AFTER `starts_at`',
                'assignable_quota' => '`assignable_quota` decimal(12,2) DEFAULT NULL COMMENT \'Quota cedibile, per cessione e delega\' AFTER `duration_months`',
                'requested_amount' => '`requested_amount` decimal(12,2) DEFAULT NULL COMMENT \'Importo richiesto, per prestito e mutuo\' AFTER `assignable_quota`',
                'age_at_start' => '`age_at_start` tinyint unsigned DEFAULT NULL COMMENT \'Età del cliente alla decorrenza, in anni\' AFTER `requested_amount`',
                'seniority_months' => '`seniority_months` smallint unsigned DEFAULT NULL COMMENT \'Anzianità di servizio in mesi alla decorrenza\' AFTER `age_at_start`',
                'disbursed_min' => '`disbursed_min` decimal(12,2) DEFAULT NULL COMMENT \'Importo erogato minimo restituito dal preventivatore\' AFTER `seniority_months`',
                'disbursed_max' => '`disbursed_max` decimal(12,2) DEFAULT NULL COMMENT \'Importo erogato massimo restituito dal preventivatore\' AFTER `disbursed_min`',
                'status' => '`status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'salvato oppure perfezionato\' AFTER `disbursed_max`',
                'pratica_id' => '`pratica_id` bigint unsigned DEFAULT NULL COMMENT \'Pratica core creata se il preventivo è stato perfezionato\' AFTER `status`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL COMMENT \'Utente core che ha concluso la simulazione\' AFTER `pratica_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `user_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'financing_quotes_fornitore_id_number_unique' => 'UNIQUE KEY `financing_quotes_fornitore_id_number_unique` (`fornitore_id`,`number`)',
            ],
            'foreign' => [
            ],
        ],
        'fornitori_listini_provvigionali' => [
            'create' => 'CREATE TABLE `fornitori_listini_provvigionali` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `fornitori_id` bigint unsigned NOT NULL COMMENT \'Fornitore del database core\',
  `listino_provvigionale_id` bigint unsigned NOT NULL COMMENT \'Listino assegnato\',
  `predefinito` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Listino predefinito del fornitore (uno solo)\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fornitore_listino` (`fornitori_id`,`listino_provvigionale_id`),
  KEY `fornitori_listini_provvigionali_listino_provvigionale_id_foreign` (`listino_provvigionale_id`),
  KEY `fornitori_listini_provvigionali_fornitori_id_index` (`fornitori_id`)
) ENGINE=InnoDB AUTO_INCREMENT=599 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Listini provvigionali assegnati ai produttori\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'fornitori_id' => '`fornitori_id` bigint unsigned NOT NULL COMMENT \'Fornitore del database core\' AFTER `id`',
                'listino_provvigionale_id' => '`listino_provvigionale_id` bigint unsigned NOT NULL COMMENT \'Listino assegnato\' AFTER `fornitori_id`',
                'predefinito' => '`predefinito` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Listino predefinito del fornitore (uno solo)\' AFTER `listino_provvigionale_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `predefinito`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'uq_fornitore_listino' => 'UNIQUE KEY `uq_fornitore_listino` (`fornitori_id`,`listino_provvigionale_id`)',
                'fornitori_listini_provvigionali_listino_provvigionale_id_foreign' => 'KEY `fornitori_listini_provvigionali_listino_provvigionale_id_foreign` (`listino_provvigionale_id`)',
                'fornitori_listini_provvigionali_fornitori_id_index' => 'KEY `fornitori_listini_provvigionali_fornitori_id_index` (`fornitori_id`)',
            ],
            'foreign' => [
                'fornitori_listini_provvigionali_listino_provvigionale_id_foreign' => 'CONSTRAINT `fornitori_listini_provvigionali_listino_provvigionale_id_foreign` FOREIGN KEY (`listino_provvigionale_id`) REFERENCES `listini_provvigionali` (`id`) ON DELETE CASCADE',
            ],
        ],
        'job_batches' => [
            'create' => 'CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del record\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del gruppo\',
  `total_jobs` int NOT NULL COMMENT \'Totale dei lavori del gruppo\',
  `pending_jobs` int NOT NULL COMMENT \'Lavori ancora da eseguire\',
  `failed_jobs` int NOT NULL COMMENT \'Numero di lavori falliti\',
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativi dei lavori falliti\',
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Opzioni serializzate\',
  `cancelled_at` int DEFAULT NULL COMMENT \'Annullamento (timestamp UNIX)\',
  `created_at` int NOT NULL COMMENT \'Creazione (timestamp UNIX)\',
  `finished_at` int DEFAULT NULL COMMENT \'Fine (timestamp UNIX)\',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Code: gruppi di lavori\'',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del record\' FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del gruppo\' AFTER `id`',
                'total_jobs' => '`total_jobs` int NOT NULL COMMENT \'Totale dei lavori del gruppo\' AFTER `name`',
                'pending_jobs' => '`pending_jobs` int NOT NULL COMMENT \'Lavori ancora da eseguire\' AFTER `total_jobs`',
                'failed_jobs' => '`failed_jobs` int NOT NULL COMMENT \'Numero di lavori falliti\' AFTER `pending_jobs`',
                'failed_job_ids' => '`failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativi dei lavori falliti\' AFTER `failed_jobs`',
                'options' => '`options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Opzioni serializzate\' AFTER `failed_job_ids`',
                'cancelled_at' => '`cancelled_at` int DEFAULT NULL COMMENT \'Annullamento (timestamp UNIX)\' AFTER `options`',
                'created_at' => '`created_at` int NOT NULL COMMENT \'Creazione (timestamp UNIX)\' AFTER `cancelled_at`',
                'finished_at' => '`finished_at` int DEFAULT NULL COMMENT \'Fine (timestamp UNIX)\' AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'jobs' => [
            'create' => 'CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome della coda\',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Contenuto del lavoro\',
  `attempts` smallint unsigned NOT NULL COMMENT \'Tentativi eseguiti\',
  `reserved_at` int unsigned DEFAULT NULL COMMENT \'Presa in carico (timestamp UNIX)\',
  `available_at` int unsigned NOT NULL COMMENT \'Quando diventa eseguibile (timestamp UNIX)\',
  `created_at` int unsigned NOT NULL COMMENT \'Creazione (timestamp UNIX)\',
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Code: lavori in attesa\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome della coda\' AFTER `id`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Contenuto del lavoro\' AFTER `queue`',
                'attempts' => '`attempts` smallint unsigned NOT NULL COMMENT \'Tentativi eseguiti\' AFTER `payload`',
                'reserved_at' => '`reserved_at` int unsigned DEFAULT NULL COMMENT \'Presa in carico (timestamp UNIX)\' AFTER `attempts`',
                'available_at' => '`available_at` int unsigned NOT NULL COMMENT \'Quando diventa eseguibile (timestamp UNIX)\' AFTER `reserved_at`',
                'created_at' => '`created_at` int unsigned NOT NULL COMMENT \'Creazione (timestamp UNIX)\' AFTER `available_at`',
            ],
            'indexes' => [
                'jobs_queue_index' => 'KEY `jobs_queue_index` (`queue`)',
            ],
            'foreign' => [
            ],
        ],
        'kyc_beneficial_owners' => [
            'create' => 'CREATE TABLE `kyc_beneficial_owners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `kyc_questionnaire_id` bigint unsigned NOT NULL COMMENT \'Questionario KYC a cui appartiene\',
  `position` tinyint unsigned NOT NULL DEFAULT \'1\' COMMENT \'Ruolo o carica nella società\',
  `client_id` bigint unsigned NOT NULL COMMENT \'persona fisica, mysql_proforma.clients.id\',
  `shares_percentage` decimal(5,2) DEFAULT NULL COMMENT \'Percentuale di quote o diritti di voto\',
  `control_criterion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Criterio di controllo (quote, voti, altro)\',
  `pep_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Condizione di persona politicamente esposta\',
  `declaration_signed_at` datetime DEFAULT NULL COMMENT \'Data della dichiarazione firmata\',
  `document_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'documents.id\',
  `is_verified` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Titolare effettivo verificato\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  KEY `kyc_beneficial_owners_kyc_questionnaire_id_foreign` (`kyc_questionnaire_id`),
  KEY `kyc_beneficial_owners_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Titolari effettivi dichiarati nel QAV (max 3 stampabili).\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'kyc_questionnaire_id' => '`kyc_questionnaire_id` bigint unsigned NOT NULL COMMENT \'Questionario KYC a cui appartiene\' AFTER `id`',
                'position' => '`position` tinyint unsigned NOT NULL DEFAULT \'1\' COMMENT \'Ruolo o carica nella società\' AFTER `kyc_questionnaire_id`',
                'client_id' => '`client_id` bigint unsigned NOT NULL COMMENT \'persona fisica, mysql_proforma.clients.id\' AFTER `position`',
                'shares_percentage' => '`shares_percentage` decimal(5,2) DEFAULT NULL COMMENT \'Percentuale di quote o diritti di voto\' AFTER `client_id`',
                'control_criterion' => '`control_criterion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Criterio di controllo (quote, voti, altro)\' AFTER `shares_percentage`',
                'pep_status' => '`pep_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Condizione di persona politicamente esposta\' AFTER `control_criterion`',
                'declaration_signed_at' => '`declaration_signed_at` datetime DEFAULT NULL COMMENT \'Data della dichiarazione firmata\' AFTER `pep_status`',
                'document_id' => '`document_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'documents.id\' AFTER `declaration_signed_at`',
                'is_verified' => '`is_verified` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Titolare effettivo verificato\' AFTER `document_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `is_verified`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'kyc_beneficial_owners_kyc_questionnaire_id_foreign' => 'KEY `kyc_beneficial_owners_kyc_questionnaire_id_foreign` (`kyc_questionnaire_id`)',
                'kyc_beneficial_owners_client_id_index' => 'KEY `kyc_beneficial_owners_client_id_index` (`client_id`)',
            ],
            'foreign' => [
            ],
        ],
        'kyc_questionnaires' => [
            'create' => 'CREATE TABLE `kyc_questionnaires` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `client_id` bigint unsigned NOT NULL COMMENT \'mysql_proforma.clients.id\',
  `client_mandate_id` bigint unsigned DEFAULT NULL COMMENT \'mysql_proforma.client_mandates.id\',
  `pratica_id` bigint unsigned DEFAULT NULL COMMENT \'mysql_proforma.pratiches.id\',
  `document_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'documents.id: QAV generato\',
  `pep_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Condizione PEP del cliente\',
  `financing_purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Scopo del finanziamento\',
  `risk_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Livello di rischio\',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'draft\' COMMENT \'Stato della verifica\',
  `compiled_at` datetime DEFAULT NULL COMMENT \'Data di compilazione\',
  `verified_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Utente che ha verificato\',
  `verified_at` datetime DEFAULT NULL COMMENT \'Data di verifica\',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note\',
  `economic_activity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Attività economica svolta\',
  `activity_sector` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Settore di attività\',
  `activity_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Luogo di svolgimento dell\'\'attività\',
  `financing_nature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Natura del finanziamento\',
  `income_band` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Fascia di reddito\',
  `wealth_band` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Fascia di patrimonio\',
  `acts_for_third_party` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Il cliente agisce per conto di terzi\',
  `legal_nature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Natura giuridica\',
  `geographic_area` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Area geografica di operatività\',
  `executor_client_id` bigint unsigned DEFAULT NULL COMMENT \'mysql_proforma.clients.id\',
  `executor_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Legame tra esecutore e cliente\',
  `executor_pep_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Condizione PEP dell\'\'esecutore\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  KEY `kyc_questionnaires_client_id_index` (`client_id`),
  KEY `kyc_questionnaires_client_mandate_id_index` (`client_mandate_id`),
  KEY `kyc_questionnaires_document_id_index` (`document_id`),
  KEY `kyc_questionnaires_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Questionari di adeguata verifica (QAV): un record per compilazione.\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'client_id' => '`client_id` bigint unsigned NOT NULL COMMENT \'mysql_proforma.clients.id\' AFTER `id`',
                'client_mandate_id' => '`client_mandate_id` bigint unsigned DEFAULT NULL COMMENT \'mysql_proforma.client_mandates.id\' AFTER `client_id`',
                'pratica_id' => '`pratica_id` bigint unsigned DEFAULT NULL COMMENT \'mysql_proforma.pratiches.id\' AFTER `client_mandate_id`',
                'document_id' => '`document_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'documents.id: QAV generato\' AFTER `pratica_id`',
                'pep_status' => '`pep_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Condizione PEP del cliente\' AFTER `document_id`',
                'financing_purpose' => '`financing_purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Scopo del finanziamento\' AFTER `pep_status`',
                'risk_level' => '`risk_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Livello di rischio\' AFTER `financing_purpose`',
                'status' => '`status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'draft\' COMMENT \'Stato della verifica\' AFTER `risk_level`',
                'compiled_at' => '`compiled_at` datetime DEFAULT NULL COMMENT \'Data di compilazione\' AFTER `status`',
                'verified_by' => '`verified_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Utente che ha verificato\' AFTER `compiled_at`',
                'verified_at' => '`verified_at` datetime DEFAULT NULL COMMENT \'Data di verifica\' AFTER `verified_by`',
                'notes' => '`notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note\' AFTER `verified_at`',
                'economic_activity' => '`economic_activity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Attività economica svolta\' AFTER `notes`',
                'activity_sector' => '`activity_sector` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Settore di attività\' AFTER `economic_activity`',
                'activity_location' => '`activity_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Luogo di svolgimento dell\'\'attività\' AFTER `activity_sector`',
                'financing_nature' => '`financing_nature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Natura del finanziamento\' AFTER `activity_location`',
                'income_band' => '`income_band` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Fascia di reddito\' AFTER `financing_nature`',
                'wealth_band' => '`wealth_band` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Fascia di patrimonio\' AFTER `income_band`',
                'acts_for_third_party' => '`acts_for_third_party` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Il cliente agisce per conto di terzi\' AFTER `wealth_band`',
                'legal_nature' => '`legal_nature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Natura giuridica\' AFTER `acts_for_third_party`',
                'geographic_area' => '`geographic_area` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Area geografica di operatività\' AFTER `legal_nature`',
                'executor_client_id' => '`executor_client_id` bigint unsigned DEFAULT NULL COMMENT \'mysql_proforma.clients.id\' AFTER `geographic_area`',
                'executor_link' => '`executor_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Legame tra esecutore e cliente\' AFTER `executor_client_id`',
                'executor_pep_status' => '`executor_pep_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Condizione PEP dell\'\'esecutore\' AFTER `executor_link`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `executor_pep_status`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'kyc_questionnaires_client_id_index' => 'KEY `kyc_questionnaires_client_id_index` (`client_id`)',
                'kyc_questionnaires_client_mandate_id_index' => 'KEY `kyc_questionnaires_client_mandate_id_index` (`client_mandate_id`)',
                'kyc_questionnaires_document_id_index' => 'KEY `kyc_questionnaires_document_id_index` (`document_id`)',
                'kyc_questionnaires_status_index' => 'KEY `kyc_questionnaires_status_index` (`status`)',
            ],
            'foreign' => [
            ],
        ],
        'listini_provvigionali' => [
            'create' => 'CREATE TABLE `listini_provvigionali` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Codice del listino (es. 0018)\',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del listino (es. Cessione Special)\',
  `tipoprodotto_id` bigint unsigned DEFAULT NULL COMMENT \'Prodotto del database core\',
  `coordinamento` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Listino per le pratiche fatte da fornitori coordinati\',
  `value` decimal(10,4) NOT NULL DEFAULT \'0.0000\' COMMENT \'Percentuale applicata alla base di tipoprodotto.tipo_provvigioni\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Listino attivo\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_listino_code_ruolo` (`code`,`coordinamento`),
  KEY `listini_provvigionali_tipoprodotto_id_index` (`tipoprodotto_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Listini provvigionali per tipo prodotto\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'code' => '`code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Codice del listino (es. 0018)\' AFTER `id`',
                'name' => '`name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del listino (es. Cessione Special)\' AFTER `code`',
                'tipoprodotto_id' => '`tipoprodotto_id` bigint unsigned DEFAULT NULL COMMENT \'Prodotto del database core\' AFTER `name`',
                'coordinamento' => '`coordinamento` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Listino per le pratiche fatte da fornitori coordinati\' AFTER `tipoprodotto_id`',
                'value' => '`value` decimal(10,4) NOT NULL DEFAULT \'0.0000\' COMMENT \'Percentuale applicata alla base di tipoprodotto.tipo_provvigioni\' AFTER `coordinamento`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Listino attivo\' AFTER `value`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `is_active`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'uq_listino_code_ruolo' => 'UNIQUE KEY `uq_listino_code_ruolo` (`code`,`coordinamento`)',
                'listini_provvigionali_tipoprodotto_id_index' => 'KEY `listini_provvigionali_tipoprodotto_id_index` (`tipoprodotto_id`)',
            ],
            'foreign' => [
            ],
        ],
        'listini_provvigionali_voci' => [
            'create' => 'CREATE TABLE `listini_provvigionali_voci` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `listino_provvigionale_id` bigint unsigned NOT NULL,
  `voce` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Voce di compenso (standard, special, banca, mediazione, polizza, consulenza, premio, brokeraggio)\',
  `percentuale` decimal(7,4) NOT NULL COMMENT \'Percentuale riconosciuta al produttore su questa voce\',
  `precaricato` decimal(3,1) NOT NULL DEFAULT \'0.0\' COMMENT \'Punti precaricati riconosciuti al produttore su questa voce\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `listini_provvigionali_voci_listino_provvigionale_id_voce_unique` (`listino_provvigionale_id`,`voce`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Percentuali di un listino provvigionale per voce di compenso (es. standard e special della cessione; banca, mediazione e polizza del mutuo)\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'listino_provvigionale_id' => '`listino_provvigionale_id` bigint unsigned NOT NULL AFTER `id`',
                'voce' => '`voce` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Voce di compenso (standard, special, banca, mediazione, polizza, consulenza, premio, brokeraggio)\' AFTER `listino_provvigionale_id`',
                'percentuale' => '`percentuale` decimal(7,4) NOT NULL COMMENT \'Percentuale riconosciuta al produttore su questa voce\' AFTER `voce`',
                'precaricato' => '`precaricato` decimal(3,1) NOT NULL DEFAULT \'0.0\' COMMENT \'Punti precaricati riconosciuti al produttore su questa voce\' AFTER `percentuale`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `precaricato`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'listini_provvigionali_voci_listino_provvigionale_id_voce_unique' => 'UNIQUE KEY `listini_provvigionali_voci_listino_provvigionale_id_voce_unique` (`listino_provvigionale_id`,`voce`)',
            ],
            'foreign' => [
                'listini_provvigionali_voci_listino_provvigionale_id_foreign' => 'CONSTRAINT `listini_provvigionali_voci_listino_provvigionale_id_foreign` FOREIGN KEY (`listino_provvigionale_id`) REFERENCES `listini_provvigionali` (`id`) ON DELETE CASCADE',
            ],
        ],
        'media' => [
            'create' => 'CREATE TABLE `media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Tipo del record proprietario\',
  `model_id` bigint unsigned NOT NULL COMMENT \'Identificativo del record proprietario\',
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Identificativo univoco del file\',
  `collection_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Raccolta del file\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome\',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del file\',
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo MIME\',
  `disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Disco di archiviazione\',
  `conversions_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Disco delle conversioni\',
  `size` bigint unsigned NOT NULL COMMENT \'Dimensione in byte\',
  `manipulations` json NOT NULL COMMENT \'Manipolazioni (JSON)\',
  `custom_properties` json NOT NULL COMMENT \'Proprietà personalizzate (JSON)\',
  `generated_conversions` json NOT NULL COMMENT \'Conversioni generate (JSON)\',
  `responsive_images` json NOT NULL COMMENT \'Immagini responsive (JSON)\',
  `order_column` int unsigned DEFAULT NULL COMMENT \'Ordine nella raccolta\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uuid_unique` (`uuid`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_order_column_index` (`order_column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'File allegati (Spatie Media Library)\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'model_type' => '`model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Tipo del record proprietario\' AFTER `id`',
                'model_id' => '`model_id` bigint unsigned NOT NULL COMMENT \'Identificativo del record proprietario\' AFTER `model_type`',
                'uuid' => '`uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Identificativo univoco del file\' AFTER `model_id`',
                'collection_name' => '`collection_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Raccolta del file\' AFTER `uuid`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome\' AFTER `collection_name`',
                'file_name' => '`file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome del file\' AFTER `name`',
                'mime_type' => '`mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo MIME\' AFTER `file_name`',
                'disk' => '`disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Disco di archiviazione\' AFTER `mime_type`',
                'conversions_disk' => '`conversions_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Disco delle conversioni\' AFTER `disk`',
                'size' => '`size` bigint unsigned NOT NULL COMMENT \'Dimensione in byte\' AFTER `conversions_disk`',
                'manipulations' => '`manipulations` json NOT NULL COMMENT \'Manipolazioni (JSON)\' AFTER `size`',
                'custom_properties' => '`custom_properties` json NOT NULL COMMENT \'Proprietà personalizzate (JSON)\' AFTER `manipulations`',
                'generated_conversions' => '`generated_conversions` json NOT NULL COMMENT \'Conversioni generate (JSON)\' AFTER `custom_properties`',
                'responsive_images' => '`responsive_images` json NOT NULL COMMENT \'Immagini responsive (JSON)\' AFTER `generated_conversions`',
                'order_column' => '`order_column` int unsigned DEFAULT NULL COMMENT \'Ordine nella raccolta\' AFTER `responsive_images`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `order_column`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'media_uuid_unique' => 'UNIQUE KEY `media_uuid_unique` (`uuid`)',
                'media_model_type_model_id_index' => 'KEY `media_model_type_model_id_index` (`model_type`,`model_id`)',
                'media_order_column_index' => 'KEY `media_order_column_index` (`order_column`)',
            ],
            'foreign' => [
            ],
        ],
        'password_reset_tokens' => [
            'create' => 'CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Email dell\'\'utente\',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Token (cifrato)\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Token di reimpostazione della password\'',
            'columns' => [
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Email dell\'\'utente\' FIRST',
                'token' => '`token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Token (cifrato)\' AFTER `email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `token`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'sessions' => [
            'create' => 'CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo della sessione\',
  `user_id` bigint unsigned DEFAULT NULL COMMENT \'Utente collegato\',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo IP\',
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Browser dell\'\'utente\',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Dati della sessione\',
  `last_activity` int NOT NULL COMMENT \'Ultima attività (timestamp UNIX)\',
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Sessioni utente\'',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo della sessione\' FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL COMMENT \'Utente collegato\' AFTER `id`',
                'ip_address' => '`ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo IP\' AFTER `user_id`',
                'user_agent' => '`user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Browser dell\'\'utente\' AFTER `ip_address`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Dati della sessione\' AFTER `user_agent`',
                'last_activity' => '`last_activity` int NOT NULL COMMENT \'Ultima attività (timestamp UNIX)\' AFTER `payload`',
            ],
            'indexes' => [
                'sessions_user_id_index' => 'KEY `sessions_user_id_index` (`user_id`)',
                'sessions_last_activity_index' => 'KEY `sessions_last_activity_index` (`last_activity`)',
            ],
            'foreign' => [
            ],
        ],
        'users' => [
            'create' => 'CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome\',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Email di accesso\',
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT \'Data di verifica dell\'\'email\',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Password (cifrata)\',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Token «ricordami»\',
  `created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Utenti dell\'\'app\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco del record\' FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome\' AFTER `id`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Email di accesso\' AFTER `name`',
                'email_verified_at' => '`email_verified_at` timestamp NULL DEFAULT NULL COMMENT \'Data di verifica dell\'\'email\' AFTER `email`',
                'password' => '`password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Password (cifrata)\' AFTER `email_verified_at`',
                'remember_token' => '`remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Token «ricordami»\' AFTER `password`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di creazione del record\' AFTER `remember_token`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora dell\'\'ultima modifica del record\' AFTER `created_at`',
            ],
            'indexes' => [
                'users_email_unique' => 'UNIQUE KEY `users_email_unique` (`email`)',
            ],
            'foreign' => [
            ],
        ],
        ];
    }
};
