<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listini_provvigionali_voci')) {
            return;
        }

        Schema::create('listini_provvigionali_voci', function (Blueprint $table) {
            $table->comment('Percentuali di un listino provvigionale per voce di compenso (es. standard e special della cessione; banca, mediazione e polizza del mutuo)');
            $table->id()->comment('Identificativo univoco del record');
            $table->foreignId('listino_provvigionale_id')->constrained('listini_provvigionali')->cascadeOnDelete()->comment('Listino a cui appartiene la voce');
            $table->string('voce', 30)->comment('Voce di compenso (standard, special, banca, mediazione, polizza, consulenza, premio, brokeraggio)');
            $table->decimal('percentuale', 7, 4)->comment('Percentuale riconosciuta al produttore su questa voce');
            $table->timestamp('created_at')->nullable()->comment('Data e ora di creazione del record');
            $table->timestamp('updated_at')->nullable()->comment('Data e ora dell\'ultima modifica del record');
            $table->unique(['listino_provvigionale_id', 'voce']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listini_provvigionali_voci');
    }
};
