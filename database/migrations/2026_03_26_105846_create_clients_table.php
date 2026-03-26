<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id()->comment('ID intero autoincrementante');
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_person')->default(true)->comment('Persona fisica (true) o giuridica (false)');
            $table->string('name')->comment('Cognome o Ragione Sociale');
            $table->string('first_name')->nullable()->comment('Nome persona fisica');
            $table->string('tax_code', 16)->nullable()->comment('Codice Fiscale');
            $table->string('vat_number', 20)->nullable()->comment('Partita IVA');
            $table->string('email')->nullable()->comment('Email di contatto principale');
            $table->string('phone', 50)->nullable()->comment('Recapito telefonico');
            // AML Flags
            $table->boolean('is_pep')->default(false)->comment('Persona Politicamente Esposta');
            $table->foreignId('client_type_id')->nullable()->constrained('client_types')->nullOnDelete();
            $table->boolean('is_sanctioned')->default(false)->comment('In liste antiterrorismo/blacklists');
            $table->boolean('is_remote_interaction')->default(false)->comment('Operatività a distanza');
            // Consensi Privacy
            $table->timestamp('general_consent_at')->nullable()->comment('Consenso generale trattamento base');
            $table->timestamp('privacy_policy_read_at')->nullable()->comment('Presa visione Art.13');
            $table->timestamp('consent_special_categories_at')->nullable()->comment('Consenso dati sanitari/giudiziari');
            $table->timestamp('consent_sic_at')->nullable()->comment('Consenso interrogazione CRIF/CTC');
            $table->timestamp('consent_marketing_at')->nullable()->comment('Consenso marketing');
            $table->timestamp('consent_profiling_at')->nullable()->comment('Consenso profilazione');
            // Status
            $table->string('status')->default('raccolta_dati')->comment('raccolta_dati|valutazione_aml|approvata|sos_inviata|chiusa');
            // Flags aggiuntivi
            $table->boolean('is_company')->default(false)->comment('Cliente è azienda fornitore');
            $table->boolean('is_lead')->default(false)->comment('Lead non ancora convertito');
            $table->foreignId('leadsource_id')->nullable()->comment('Client che ha fornito il lead');
            $table->timestamp('acquired_at')->nullable()->comment('Data acquisizione contatto');
            $table->string('contoCOGE')->nullable()->comment('Conto COGE');
            $table->boolean('privacy_consent')->default(false)->comment('Consenso privacy');
            $table->boolean('is_client')->default(true)->comment('Contraente contratto');
            $table->text('subfornitori')->nullable()->comment('Subfornitori da comunicare');
            $table->boolean('is_requiredApprovation')->default(false)->comment('Da approvare per gradimento');
            $table->boolean('is_approved')->default(true)->comment('Approvata per gradimento');
            $table->boolean('is_anonymous')->default(false)->comment('Cliente anonimo');
            $table->timestamp('blacklist_at')->nullable()->comment('Data inserimento in blacklist');
            $table->string('blacklisted_by')->nullable()->comment('Utente che ha inserito in blacklist');
            $table->decimal('salary', 10, 2)->nullable()->comment('Retribuzione annuale');
            $table->decimal('salary_quote', 10, 2)->nullable()->comment('Quota retribuzione');
            $table->boolean('is_art108')->default(false)->comment('Esente art.108');
            $table->timestamps();

            // Indexes
            $table->index('blacklist_at');
            $table->index('is_anonymous');
            $table->index('is_approved');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('leadsource_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
