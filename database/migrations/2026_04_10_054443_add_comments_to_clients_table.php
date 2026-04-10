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
        Schema::table('clients', function (Blueprint $table) {
            $table->tinyInteger('is_pep')->default(0)->comment('Antiriciclaggio (AML): Politically Exposed Person (Persona Politicamente Esposta)')->change();
            $table->tinyInteger('is_sanctioned')->default(0)->comment('Antiriciclaggio (AML): Soggetto presente in liste sanzionatorie internazionali')->change();
            $table->tinyInteger('is_remote_interaction')->default(0)->comment("Indica se l'adeguata verifica è avvenuta a distanza (senza presenza fisica)")->change();
            $table->tinyInteger('is_ghost')->default(0)->comment('Profilo fittizio/incompleto usato per simulazioni o preventivi veloci prima del KYC')->change();
            $table->string('status', 255)->default('raccolta_dati')->comment('Stato nel funnel BPM (es. lead, raccolta_dati, istruttoria, approvato)')->change();
            $table->string('contoCOGE', 255)->nullable()->comment('Riferimento al Conto di Contabilità Generale per integrazione ERP')->change();
            $table->tinyInteger('is_art108')->default(0)->comment('Riferimento normativo specifico (es. Art. 108 TUB/TUIR per cessione del quinto o credito)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->tinyInteger('is_pep')->default(0)->change();
            $table->tinyInteger('is_sanctioned')->default(0)->change();
            $table->tinyInteger('is_remote_interaction')->default(0)->change();
            $table->tinyInteger('is_ghost')->default(0)->change();
            $table->string('status', 255)->default('raccolta_dati')->change();
            $table->string('contoCOGE', 255)->nullable()->change();
            $table->tinyInteger('is_art108')->default(0)->change();
        });
    }
};
