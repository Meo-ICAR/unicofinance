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
        Schema::table('task_executions', function (Blueprint $table) {
            $table->string('status', 255)->default('todo')->comment("Macchina a stati dell'esecuzione (es. todo, in_progress, waiting_client, done, blocked)")->change();
            $table->string('audit_dms_id', 255)->nullable()->comment('ID univoco del pacchetto documentale salvato nel DMS (Document Management System) esterno a fini di Audit')->change();
            $table->unsignedBigInteger('previous_task_execution_id')->nullable()->comment('Puntatore per ricostruire la catena storica (Linked List) di come la pratica ha viaggiato tra le scrivanie')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_executions', function (Blueprint $table) {
            $table->string('status', 255)->default('todo')->change();
            $table->string('audit_dms_id', 255)->nullable()->change();
            $table->unsignedBigInteger('previous_task_execution_id')->nullable()->change();
        });
    }
};
