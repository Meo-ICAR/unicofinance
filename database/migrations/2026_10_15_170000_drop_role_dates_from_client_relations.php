<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Le date di inizio e fine ruolo stanno nella visura: non servono al mediatore. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            $table->dropColumn(['data_inizio_ruolo', 'data_fine_ruolo']);
        });
    }

    public function down(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            $table->date('data_inizio_ruolo')->nullable();
            $table->date('data_fine_ruolo')->nullable();
        });
    }
};
