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
        Schema::table('privacy_legal_bases', function (Blueprint $table) {
            $table->comment('Catalogo delle basi giuridiche del trattamento (Es: Art.6 c.1 lett. a Consenso, lett. b Contratto, lett. f Legittimo Interesse)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('privacy_legal_bases', function (Blueprint $table) {
            $table->comment('');
        });
    }
};
