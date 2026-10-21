<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pratica_leads')) {
            return;
        }

        Schema::create('pratica_leads', function (Blueprint $table) {
            $table->comment('Lead trasmessi a Mediafacile alla creazione della pratica');
            $table->id()->comment('Identificativo del tentativo di trasmissione');
            $table->unsignedBigInteger('pratica_id')->index()->comment('Pratica da cui è partito il lead');
            $table->boolean('is_ok')->default(false)->comment('Esito: 1 = caricato correttamente (Stato OK), 0 = errore');
            $table->string('stato')->nullable()->comment('Stato restituito dal servizio (OK oppure KO - motivazione)');
            $table->string('iduu')->nullable()->comment('ID univoco del lead creato su Mediafacile (IDUU)');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Utente che ha trasmesso il lead');
            $table->timestamp('created_at')->nullable()->comment('Data e ora della trasmissione');
            $table->timestamp('updated_at')->nullable()->comment('Ultima modifica del record');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pratica_leads');
    }
};
