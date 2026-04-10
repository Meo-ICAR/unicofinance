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
        Schema::table('data_breaches', function (Blueprint $table) {
            $table->comment("Registro delle violazioni dei dati personali ai sensi dell'Art. 33 del GDPR");
            $table->tinyInteger('reported_to_dpa')->default(0)->comment('Flag che indica se la violazione è stata formalmente notificata al Garante della Privacy')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_breaches', function (Blueprint $table) {
            $table->comment('');
            $table->tinyInteger('reported_to_dpa')->default(0)->change();
        });
    }
};
