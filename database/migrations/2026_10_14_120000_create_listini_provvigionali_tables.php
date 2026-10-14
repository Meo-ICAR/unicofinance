<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Le tabelle provvigionali stanno sul database core, accanto a prodotti e fornitori. */
    public function getConnection()
    {
        return config('unico-core.connection');
    }

    public function up(): void
    {
        Schema::create('listini_provvigionali', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Codice del listino (es. 0018)');
            $table->string('name', 100)->comment('Nome del listino (es. Cessione Special)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Percentuale del listino per prodotto. La base di calcolo
        // (lordo, erogato, netto) è tipoprodotto.tipo_provvigioni.
        Schema::create('listini_provvigionali_prodotti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listino_provvigionale_id')->constrained('listini_provvigionali')->cascadeOnDelete();
            $table->unsignedBigInteger('tipoprodotto_id')->index();
            $table->decimal('value', 10, 4)->default(0)->comment('Percentuale applicata alla base di tipoprodotto.tipo_provvigioni');
            $table->timestamps();
            $table->unique(['listino_provvigionale_id', 'tipoprodotto_id'], 'uq_listino_prodotto');
        });

        Schema::create('fornitori_listini_provvigionali', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fornitori_id')->index();
            $table->foreignId('listino_provvigionale_id')->constrained('listini_provvigionali')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fornitori_id', 'listino_provvigionale_id'], 'uq_fornitore_listino');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornitori_listini_provvigionali');
        Schema::dropIfExists('listini_provvigionali_prodotti');
        Schema::dropIfExists('listini_provvigionali');
    }
};
