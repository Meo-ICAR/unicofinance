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
        Schema::table('raci_assignments', function (Blueprint $table) {
            $table->index('process_task_id', 'raci_assignments_process_task_id_idx');
            $table->index('business_function_id', 'raci_assignments_business_function_id_idx');
            $table->dropUnique('unique_raci_task');
            $table->unique(['process_task_id', 'role'], 'unique_raci_task_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raci_assignments', function (Blueprint $table) {
            $table->dropUnique('unique_raci_task_role');
            $table->unique(['process_task_id', 'business_function_id'], 'unique_raci_task');
            $table->dropIndex('raci_assignments_process_task_id_idx');
            $table->dropIndex('raci_assignments_business_function_id_idx');
        });
    }
};
