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
        Schema::table('task_deadlines', function (Blueprint $table) {
            $table->enum('status', ['active', 'warning', 'breached', 'completed'])->default('active')->comment('Stato calcolato dinamicamente. Breached = SLA violato (ritardo)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_deadlines', function (Blueprint $table) {
            $table->enum('status', ['active', 'warning', 'breached', 'completed'])->default('active')->change();
        });
    }
};
