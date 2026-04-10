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
        Schema::table('request_registries', function (Blueprint $table) {
            $table->foreignId('active_process_id')->nullable()->after('assigned_to')->comment('Riferimento rapido al processo BPM attualmente attivo per questa richiesta')->constrained('processes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_registries', function (Blueprint $table) {
            $table->dropForeign(['active_process_id']);
            $table->dropColumn('active_process_id');
        });
    }
};
