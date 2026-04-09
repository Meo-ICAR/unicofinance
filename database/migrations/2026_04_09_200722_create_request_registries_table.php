<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_registries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();

            // Identificativo univoco della richiesta
            $table->string('request_number')->unique();

            // Data di ricezione e canale
            $table->date('request_date');
            $table->enum('received_via', [
                'email',
                'pec',
                'telefono',
                'raccomandata',
                'portale',
                'di_persona',
            ])->default('email');

            // Chi ha fatto la richiesta
            $table->enum('requester_type', [
                'interessato',
                'mandatario',
                'organismo_vigilanza',
            ])->default('interessato');
            $table->string('requester_name');
            $table->string('requester_contact')->nullable();

            // Dati mandatario (nullable, solo se requester_type = 'mandatario')
            $table->string('mandate_reference')->nullable()->comment('Numero procura, data, notaio');

            // Dati organismo di vigilanza (nullable, solo se requester_type = 'organismo_vigilanza')
            $table->string('oversight_body_type')->nullable()->comment('Garante, ARERA, AGCM, altro');

            // Tipo di richiesta
            $table->enum('request_type', [
                'accesso',
                'cancellazione',
                'rettifica',
                'opposizione',
                'limitazione',
                'portabilita',
                'revoca_consenso',
                'reclamazione',
            ]);

            // Riferimento polimorfico al soggetto interessato
            $table->string('data_subject_type')->nullable();
            $table->unsignedBigInteger('data_subject_id')->nullable();
            $table->index(['data_subject_type', 'data_subject_id']);

            // Descrizione della richiesta
            $table->text('description')->nullable();

            // Stato e scadenze
            $table->enum('status', [
                'ricevuta',
                'in_lavorazione',
                'evasa',
                'respinta',
                'parzialmente_evasa',
                'scaduta',
            ])->default('ricevuta');
            $table->date('response_deadline')->comment('request_date + 30 giorni (Art. 12 GDPR)');
            $table->date('response_date')->nullable();
            $table->text('response_summary')->nullable();

            // SLA tracking
            $table->boolean('sla_breach')->default(false);
            $table->boolean('extension_granted')->default(false);
            $table->text('extension_reason')->nullable();

            // Assegnazione
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_registries');
    }
};
