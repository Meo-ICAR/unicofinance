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
            $table->foreignId('process_task_id')->nullable()->after('active_process_id')->comment('Task specifico del processo BPM a cui è arrivata la richiesta (scrivania corrente)')->constrained('process_tasks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_registries', function (Blueprint $table) {
            $table->dropForeign(['process_task_id']);
            $table->dropColumn('process_task_id');
        });
    }
};
