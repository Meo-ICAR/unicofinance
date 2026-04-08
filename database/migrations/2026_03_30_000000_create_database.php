<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ==========================================
        // FASE 1: TABELLE INDIPENDENTI (Dizionari e Core)
        // ==========================================

        Schema::create('companies', function (Blueprint $table) {
            $table->comment('Tenant principale: Società di mediazione, Call Center, Sw House. Contiene dati legali e configurazioni globali.');
            $table->uuid('id')->primary()->comment('UUID v4 generato da Laravel');
            $table->string('name')->comment("Ragione Sociale dell'azienda");
            $table->string('vat_number', 50)->nullable()->comment('Partita IVA');
            $table->string('vat_name', 50)->nullable()->comment('Intestazione Partita IVA');
            $table->string('oam', 50)->nullable()->comment('Numero di iscrizione OAM');
            $table->date('oam_at')->nullable();
            $table->string('oam_name')->nullable();
            $table->string('numero_iscrizione_rui')->nullable();
            $table->string('ivass', 30)->nullable();
            $table->date('ivass_at')->nullable();
            $table->string('ivass_name')->nullable();
            $table->enum('ivass_section', ['A', 'B', 'C', 'D', 'E'])->nullable();
            $table->string('sponsor')->nullable();
            $table->enum('company_type', ['mediatore', 'call center', 'hotel', 'sw house'])->nullable();
            $table->text('page_header')->nullable();
            $table->text('page_footer')->nullable();

            // SMTP Configs
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('smtp_from_email')->nullable();
            $table->string('smtp_from_name')->nullable();
            $table->boolean('smtp_enabled')->default(false);
            $table->boolean('smtp_verify_ssl')->default(true);
            $table->timestamps();
        });

        Schema::create('client_types', function (Blueprint $table) {
            $table->comment('Tassonomia Clienti: Privato, PMI, Corporate, ecc.');
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('privacy_data_types', function (Blueprint $table) {
            $table->comment('Catalogo delle tipologie di dati trattati a norma GDPR (Comuni, Particolari, Giudiziari).');
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->enum('category', ['comuni', 'particolari', 'giudiziari']);
            $table->integer('retention_years')->default(10);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->comment('Politiche di Service Level Agreement per il calcolo dei tempi.');
            $table->id();
            $table->string('name');
            $table->string('process_type');
            $table->integer('duration_minutes');
            $table->integer('warning_threshold_minutes');
            $table->boolean('exclude_weekends')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->comment('Calendario Festività per motore SLA.');
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_recurring')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        // ==========================================
        // FASE 2: MODIFICHE A TABELLE ESISTENTI
        // ==========================================

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->comment("Utente approvato per l'accesso al sistema");
            $table->string('company_name')->nullable()->comment('Nome azienda inserito in fase di registrazione');
            $table->boolean('is_rejected')->default(false);
            $table->boolean('is_super_admin')->default(false);
            $table->foreignUuid('current_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->softDeletes();
        });

        // ==========================================
        // FASE 3: DIPENDENZE DI LIVELLO 1
        // ==========================================

        Schema::create('company_user', function (Blueprint $table) {
            $table->comment('Tabella Pivot: Associa utenti alle aziende definendone il ruolo.');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('role')->default('user');
            $table->timestamps();
            $table->primary(['user_id', 'company_id']);
        });

        Schema::create('company_branches', function (Blueprint $table) {
            $table->comment("Filiali fisiche dell'azienda.");
            $table->id();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_main_office')->default(false);
            $table->string('manager_first_name', 100)->nullable();
            $table->string('manager_last_name', 100)->nullable();
            $table->string('manager_tax_code', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('business_functions', function (Blueprint $table) {
            $table->comment('Organigramma aziendale (Owner dei processi GDPR).');
            $table->id();
            $table->string('code');
            $table->string('macro_area');
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->string('outsourcable_status')->default('no');
            $table->foreignId('managed_by_id')->nullable()->constrained('business_functions')->nullOnDelete();
            $table->longText('mission')->nullable();
            $table->longText('responsibility')->nullable();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        // ==========================================
        // FASE 4: DIPENDENZE DI LIVELLO 2
        // ==========================================

        Schema::create('employees', function (Blueprint $table) {
            $table->comment('Anagrafica dipendenti reali. Ruoli privacy e firme digitali.');
            $table->id();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('role_title', 100)->nullable();
            $table->string('cf')->nullable();
            $table->string('email')->nullable();
            $table->string('pec')->nullable();
            $table->string('phone')->nullable();
            $table->string('department', 100)->nullable();

            $table->string('oam', 100)->nullable();
            $table->date('oam_at')->nullable();
            $table->string('oam_name', 100)->nullable();
            $table->string('numero_iscrizione_rui', 50)->nullable();
            $table->date('oam_dismissed_at')->nullable();
            $table->string('ivass', 100)->nullable();

            $table->date('hiring_date')->nullable();
            $table->date('termination_date')->nullable();
            // Dipende da company_branches
            $table->foreignId('company_branch_id')->nullable()->constrained('company_branches')->nullOnDelete();
            $table->foreignId('coordinated_by_id')->nullable()->constrained('employees')->nullOnDelete();

            // GDPR
            $table->string('employee_types')->default('dipendente');
            $table->string('supervisor_type')->default('no');
            $table->string('privacy_role')->nullable();
            $table->text('purpose')->nullable();
            $table->text('data_subjects')->nullable();
            $table->text('data_categories')->nullable();
            $table->string('retention_period')->nullable();
            $table->string('extra_eu_transfer')->nullable();
            $table->text('security_measures')->nullable();
            $table->string('privacy_data')->nullable();
            $table->boolean('is_structure')->default(false);
            $table->boolean('is_ghost')->default(false);

            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->comment('Anagrafica Clienti e log consensi GDPR.');
            $table->id();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_person')->default(true);
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('tax_code', 16)->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_pep')->default(false);
            $table->boolean('is_sanctioned')->default(false);
            $table->boolean('is_remote_interaction')->default(false);

            $table->timestamp('general_consent_at')->nullable();
            $table->timestamp('privacy_policy_read_at')->nullable();
            $table->timestamp('consent_special_categories_at')->nullable();
            $table->timestamp('consent_sic_at')->nullable();
            $table->timestamp('consent_marketing_at')->nullable();
            $table->timestamp('consent_profiling_at')->nullable();

              // GDPR
            $table->string('privacy_role')->nullable();
            $table->text('purpose')->nullable();
            $table->text('data_subjects')->nullable();
            $table->text('data_categories')->nullable();
            $table->string('retention_period')->nullable();
            $table->string('extra_eu_transfer')->nullable();
            $table->text('security_measures')->nullable();
            $table->string('privacy_data')->nullable();
            $table->boolean('is_structure')->default(false);
            $table->boolean('is_ghost')->default(false);

            // Dipende da client_types
            $table->foreignId('client_type_id')->nullable()->constrained('client_types')->nullOnDelete();

            $table->string('status')->default('raccolta_dati');
            $table->boolean('is_company')->default(false);
            $table->boolean('is_lead')->default(false);
            $table->foreignId('leadsource_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestamp('acquired_at')->nullable();
            $table->string('contoCOGE')->nullable();
            $table->boolean('privacy_consent')->default(false);
            $table->boolean('is_client')->default(true);
            $table->text('subfornitori')->nullable();
            $table->boolean('is_requiredApprovation')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('blacklist_at')->nullable();
            $table->string('blacklisted_by')->nullable();

            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('salary_quote', 10, 2)->nullable();
            $table->boolean('is_art108')->default(false);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('blacklist_at');
            $table->index('is_anonymous');
            $table->index('is_approved');
        });

        // ==========================================
        // FASE 5: PIVOT E PROCESSI BASE
        // ==========================================

        Schema::create('business_function_employee', function (Blueprint $table) {
            $table->comment('Pivot: Associa dipendenti a funzioni aziendali (organigramma).');
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('is_manager')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('temporary_reason')->nullable();
            $table->timestamps();
            $table->primary(['business_function_id', 'employee_id']);
        });

        Schema::create('business_function_client', function (Blueprint $table) {
            $table->comment('Pivot: Incarichi tra Clienti e Funzioni Aziendali.');
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('temporary_reason')->nullable();
            $table->timestamps();
            $table->primary(['business_function_id', 'client_id']);
        });

        Schema::create('processes', function (Blueprint $table) {
            $table->comment('Catalogo dei Processi Aziendali (BPM).');
            $table->id();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->foreignId('owner_function_id')->nullable()->constrained('business_functions')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('target_model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ==========================================
        // FASE 6: STRUTTURA DEI TASK BPM
        // ==========================================

        Schema::create('process_tasks', function (Blueprint $table) {
            $table->comment('Passaggi che compongono un processo (Le "Scrivanie").');
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->integer('sequence_number')->unsigned()->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ==========================================
        // FASE 7: REGOLE E GDPR SUI TASK
        // ==========================================

        Schema::create('raci_assignments', function (Blueprint $table) {
            $table->comment('Matrice RACI su ogni task.');
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->enum('role', ['R', 'A', 'C', 'I']);
            $table->timestamps();
            $table->unique(['process_task_id', 'business_function_id'], 'unique_raci_task');
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->comment('Gruppi logici di controlli associati a un Task.');
            $table->id();
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

Schema::create('privacy_legal_bases', function (Blueprint $table) {
    $table->id();
    $table->string('name')->comment('Nome breve: Consenso, Contratto, ecc.');
    $table->string('reference_article')->default('Art. 6 par. 1 lett. ...');
    $table->text('description')->nullable()->comment('Spiegazione estesa della base giuridica');
    $table->timestamps();
});


      Schema::create('process_task_privacy_data', function (Blueprint $table) {
    $table->comment('Registro Trattamenti: Dettaglio del trattamento dati personali per ogni task.');

    // Chiavi Esterne
    $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();
    $table->foreignId('privacy_data_type_id')->constrained('privacy_data_types')->cascadeOnDelete();

    // Dettagli del Trattamento
    $table->enum('access_level', ['read', 'write', 'update', 'delete'])->default('read');

    // Finalità e Base Giuridica (GDPR Compliance)
    $table->string('purpose')->nullable()->comment('Finalità specifica del trattamento in questo task');
    $table->foreignId('privacy_legal_base_id')->nullable()->constrained('privacy_legal_bases');
    // 'Es: Consenso, Contratto, Obbligo Legale, Legittimo Interesse');


    // Sicurezza e Conservazione
    $table->string('retention_period')->nullable()->comment('Tempo di conservazione dei dati relativi a questo task');
    $table->boolean('is_encrypted')->default(false)->comment('Il dato è cifrato durante questo task?');
    $table->boolean('is_shared_externally')->default(false)->comment('Il dato viene inviato a terzi in questa fase?');

    // Audit
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    // Chiave Primaria Composta
    $table->primary(['process_task_id', 'privacy_data_type_id'], 'task_privacy_primary');
});

        // ==========================================
        // FASE 8: ELEMENTI DELLA CHECKLIST E ESECUZIONE (TICKET)
        // ==========================================

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->comment('Le singole regole/istruzioni operative nel task.');
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->text('instruction');
            $table->boolean('is_mandatory')->default(true);
            $table->string('require_condition_class')->nullable();
            $table->string('skip_condition_class')->nullable();
            $table->integer('sort_order')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_executions', function (Blueprint $table) {
            $table->comment("Istanza reale di esecuzione (Ticket/Pratica). Base per l'Audit Trail.");
            $table->id();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->string('reference_number')->nullable();
            $table->string('audit_dms_id')->nullable();
            $table->string('status')->default('todo');
            $table->foreignId('previous_task_execution_id')->nullable()->constrained('task_executions')->nullOnDelete();

            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
        });

        // ==========================================
        // FASE 9: AUDIT STORICO E DEADLINES (Le massime dipendenze)
        // ==========================================

        Schema::create('task_execution_checklist_items', function (Blueprint $table) {
            $table->comment('Dato IMMUTABILE. Snapshot storico della regola applicata e firmata.');
            $table->id();
            $table->foreignId('task_execution_id')->constrained('task_executions')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained('checklist_items')->nullOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_not_applicable')->default(false);
            $table->boolean('automated_by_system')->default(false);
            $table->boolean('requires_revision')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_deadlines', function (Blueprint $table) {
            $table->comment('Scadenziario puntuale SLA applicato alla singola esecuzione.');
            $table->id();
            $table->foreignId('task_execution_id')->constrained('task_executions')->cascadeOnDelete();
            $table->foreignId('sla_policy_id')->constrained('sla_policies');
            $table->dateTime('start_time');
            $table->dateTime('warning_at');
            $table->dateTime('due_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['active', 'warning', 'breached', 'completed'])->default('active');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Rimuove rigorosamente nell'ordine inverso alla creazione (Dalla Fase 9 alla Fase 1)
        Schema::dropIfExists('task_deadlines');
        Schema::dropIfExists('task_execution_checklist_items');
        Schema::dropIfExists('task_executions');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('process_task_privacy_data');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('raci_assignments');
        Schema::dropIfExists('process_tasks');
        Schema::dropIfExists('processes');
        Schema::dropIfExists('business_function_client');
        Schema::dropIfExists('business_function_employee');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('business_functions');
        Schema::dropIfExists('company_branches');
        Schema::dropIfExists('company_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_company_id']);
            $table->dropColumn(['is_approved', 'company_name', 'is_rejected', 'is_super_admin', 'current_company_id', 'created_by', 'updated_by', 'deleted_by']);
            $table->dropSoftDeletes();
        });

        // Elimina per ultime le tabelle senza dipendenze esterne
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('privacy_data_types');
        Schema::dropIfExists('client_types');
        Schema::dropIfExists('companies');
    }
};
