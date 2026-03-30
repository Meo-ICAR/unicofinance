<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mattiverse\Userstamps\Traits\Userstamps;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id()->comment('ID univoco dipendente');
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('ID utente collegato');
            $table->string('name')->nullable()->comment('Nome completo dipendente');
            $table->string('role_title', 100)->nullable()->comment('Qualifica aziendale');
            $table->string('cf')->nullable()->comment('Codice Fiscale');
            $table->string('email')->nullable()->comment('Email aziendale');
            $table->string('pec')->nullable()->comment('PEC');
            $table->string('phone')->nullable()->comment('Telefono');
            $table->string('department', 100)->nullable()->comment('Dipartimento');
            // OAM / IVASS
            $table->string('oam', 100)->nullable()->comment('Codice OAM individuale');
            $table->date('oam_at')->nullable()->comment('Data iscrizione OAM');
            $table->string('oam_name', 100)->nullable()->comment('Nome OAM');
            $table->string('numero_iscrizione_rui', 50)->nullable()->comment('Numero iscrizione RUI');
            $table->date('oam_dismissed_at')->nullable()->comment('Data revoca OAM');
            $table->string('ivass', 100)->nullable()->comment('Codice IVASS individuale');
            $table->date('hiring_date')->nullable()->comment('Data assunzione');
            $table->date('termination_date')->nullable()->comment('Data fine rapporto');
            // Relations
            $table->foreignId('company_branch_id')->nullable()->constrained('company_branches')->nullOnDelete()->comment('Sede fisica');
            $table->foreignId('coordinated_by_id')->nullable()->comment('Coordinatore (altro employee)');
            // Roles & Types
            $table->string('employee_types')->default('dipendente')->comment('Tipologia di dipendente');
            $table->string('supervisor_type')->default('no')->comment('Se supervisore');
            // Privacy GDPR
            $table->string('privacy_role')->nullable()->comment('Ruolo Privacy');
            $table->text('purpose')->nullable()->comment('Finalità del trattamento');
            $table->text('data_subjects')->nullable()->comment('Categorie di Interessati');
            $table->text('data_categories')->nullable()->comment('Categorie di Dati Trattati');
            $table->string('retention_period')->nullable()->comment('Tempi di conservazione');
            $table->string('extra_eu_transfer')->nullable()->comment('Trasferimento Extra-UE');
            $table->text('security_measures')->nullable()->comment('Misure di sicurezza');
            $table->string('privacy_data')->nullable()->comment('Altri dati privacy');
            // Flags
            $table->boolean('is_structure')->default(false)->comment('Personale di struttura');
            $table->boolean('is_ghost')->default(false)->comment('Personale prestato');
            $table->timestamps();  // Crea created_at e updated_at

            // LA MAGIA DEL PACCHETTO:
            $table->userstamps();  // Crea in automatico created_by e updated_by

            // SE USI I SOFT DELETES:
            $table->softDeletes();
            $table->userstampSoftDeletes();

            // Self-referencing FK (added after table creation)
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('coordinated_by_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
