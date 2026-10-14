<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un listino è legato a un solo prodotto e a un ruolo (diretto o coordinatore).
        // La base di calcolo della % (lordo, erogato, netto) è tipoprodotto.tipo_provvigioni.
        Schema::create('listini_provvigionali', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->comment('Codice del listino (es. 0018)');
            $table->string('name', 100)->comment('Nome del listino (es. Cessione Special)');
            $table->unsignedBigInteger('tipoprodotto_id')->nullable()->index()->comment('Prodotto del database core');
            $table->boolean('coordinamento')->default(false)->comment('Listino per le pratiche fatte da fornitori coordinati');
            $table->decimal('value', 10, 4)->default(0)->comment('Percentuale applicata alla base di tipoprodotto.tipo_provvigioni');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code', 'coordinamento'], 'uq_listino_code_ruolo');
        });

        Schema::create('fornitori_listini_provvigionali', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fornitori_id')->index()->comment('Fornitore del database core');
            $table->foreignId('listino_provvigionale_id')->constrained('listini_provvigionali')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fornitori_id', 'listino_provvigionale_id'], 'uq_fornitore_listino');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornitori_listini_provvigionali');
        Schema::dropIfExists('listini_provvigionali');
    }
};
