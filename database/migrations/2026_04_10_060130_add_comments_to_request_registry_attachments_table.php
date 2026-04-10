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
        Schema::table('request_registry_attachments', function (Blueprint $table) {
            $table->enum('file_type', ['richiesta', 'documento_identita', 'procura_mandato', 'risposta', 'documentazione_interna'])->default('richiesta')->comment("Classificazione del file per gestire la retention (i documenti d'identità vanno cancellati prima)")->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_registry_attachments', function (Blueprint $table) {
            $table->enum('file_type', ['richiesta', 'documento_identita', 'procura_mandato', 'risposta', 'documentazione_interna'])->default('richiesta')->change();
        });
    }
};
