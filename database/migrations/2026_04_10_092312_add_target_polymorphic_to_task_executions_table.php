<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_executions', function (Blueprint $table) {
            $table->string('target_type')->nullable()->after('previous_task_execution_id');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->index(['target_type', 'target_id'], 'task_executions_target_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_executions', function (Blueprint $table) {
            $table->dropIndex('task_executions_target_index');
            $table->dropColumn(['target_type', 'target_id']);
        });
    }
};
