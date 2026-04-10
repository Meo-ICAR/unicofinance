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
        Schema::table('consent_logs', function (Blueprint $table) {
            $table->comment('Registro immutabile (Log di Audit) di tutti i consensi privacy prestati o revocati dagli utenti');
            $table->string('origin', 255)->nullable()->comment('Sorgente di acquisizione del consenso (es. Website form, Facebook Ads, API)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consent_logs', function (Blueprint $table) {
            $table->comment('');
            $table->string('origin', 255)->nullable()->change();
        });
    }
};
