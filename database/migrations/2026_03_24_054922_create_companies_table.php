<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v4 generato da Laravel (Chiave Primaria)');
            $table->string('name')->comment('Ragione Sociale della società di mediazione');
            $table->string('vat_number', 50)->nullable()->comment("Partita IVA o Codice Fiscale dell'agenzia");
            $table->string('vat_name', 50)->nullable()->comment('Denominazione fiscale per fatturazione');
            $table->string('oam', 50)->nullable()->comment('Numero iscrizione OAM');
            $table->date('oam_at')->nullable()->comment('Data iscrizione OAM');
            $table->string('oam_name')->nullable()->comment('Nome registrato negli elenchi OAM');
            $table->string('numero_iscrizione_rui')->nullable();
            $table->string('ivass', 30)->nullable()->comment('Codice di iscrizione IVASS');
            $table->date('ivass_at')->nullable()->comment('Data iscrizione IVASS');
            $table->string('ivass_name')->nullable()->comment('Denominazione IVASS');
            $table->enum('ivass_section', ['A', 'B', 'C', 'D', 'E'])->nullable()->comment('Sezione IVASS');
            $table->string('sponsor')->nullable()->comment('Provenienza del ns. cliente');
            $table->enum('company_type', ['mediatore', 'call center', 'hotel', 'sw house'])->nullable()->comment('Tipologia società');
            $table->text('page_header')->nullable()->comment('Intestazione per carta intestata');
            $table->text('page_footer')->nullable()->comment('Piè di pagina per carta intestata');
            $table->timestamps();
            $table->string('smtp_host')->nullable()->comment('Host server SMTP per invio email');
            $table->integer('smtp_port')->nullable()->comment('Porta server SMTP');
            $table->string('smtp_username')->nullable()->comment('Username SMTP');
            $table->string('smtp_password')->nullable()->comment('Password SMTP (encrypted)');
            $table->string('smtp_encryption')->nullable()->comment('Tipo crittografia SMTP (tls, ssl)');
            $table->string('smtp_from_email')->nullable()->comment('Email mittente per invio SMTP');
            $table->string('smtp_from_name')->nullable()->comment('Nome mittente per invio SMTP');
            $table->boolean('smtp_enabled')->default(false)->comment('Abilita invio email tramite SMTP');
            $table->boolean('smtp_verify_ssl')->default(true)->comment('Verifica certificato SSL SMTP');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->comment = 'Tabella principale dei Tenant (Società di Mediazione Creditizia).';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
