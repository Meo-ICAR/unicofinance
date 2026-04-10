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
        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->comment('Registro globale di opt-out e blocchi comunicazioni. Implementa il principio di Privacy by Design.');
            $table->string('hashed_identifier', 255)->comment("Hash SHA-256 (non decriptabile) dell'email o telefono per evitare di conservare il dato in chiaro in blacklist")->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->comment('');
            $table->string('hashed_identifier', 255)->change();
        });
    }
};
