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
        Schema::create('process_macro_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->comment('Codice identificativo univoco (es. CORE, COMPL, SUPP)');
            $table->string('name', 255)->comment('Nome della macro-categoria');
            $table->text('description')->comment('Descrizione estesa della tipologia di processi');
            $table->tinyInteger('is_active')->default(1)->comment('Stato di attivazione');
            $table->timestamps();
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_macro_categories');
    }
};
