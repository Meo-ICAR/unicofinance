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
        Schema::table('request_registries', function (Blueprint $table) {
            $table->comment('Registro Data Subject Requests (DSR) per la gestione legale delle richieste privacy (Accesso, Cancellazione, ecc.)');
            $table->enum('request_type', ['accesso', 'cancellazione', 'rettifica', 'opposizione', 'limitazione', 'portabilita', 'revoca_consenso', 'reclamazione'])->comment('Mappatura diretta degli Articoli da 15 a 22 del GDPR')->change();
            $table->date('response_deadline')->comment('Scadenza di legge: obbligatoriamente calcolata come request_date + 30 giorni calendario (Art. 12 GDPR)')->change();
            $table->tinyInteger('sla_breach')->default(0)->comment('Violazione normativa: la risposta è stata fornita oltre il termine legale dei 30 giorni')->change();
            $table->tinyInteger('extension_granted')->default(0)->comment('Art. 12(3) GDPR: Possibilità di estendere il termine di ulteriori 60 giorni per richieste complesse')->change();
            $table->string('mandate_reference', 255)->nullable()->comment('Dati della procura se la richiesta arriva tramite avvocato/mandatario')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_registries', function (Blueprint $table) {
            $table->comment('');
            $table->enum('request_type', ['accesso', 'cancellazione', 'rettifica', 'opposizione', 'limitazione', 'portabilita', 'revoca_consenso', 'reclamazione'])->change();
            $table->date('response_deadline')->change();
            $table->tinyInteger('sla_breach')->default(0)->change();
            $table->tinyInteger('extension_granted')->default(0)->change();
            $table->string('mandate_reference', 255)->nullable()->change();
        });
    }
};
