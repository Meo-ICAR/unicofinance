<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_execution_id')->constrained('task_executions')->onDelete('cascade')->comment('Collega al task execution');
            $table->foreignId('sla_policy_id')->constrained('sla_policies')->comment('Policy SLA applicata');

            $table->dateTime('start_time')->comment('Quando il task è iniziato');
            $table->dateTime('warning_at')->comment('Calcolata via codice - quando avvisare');
            $table->dateTime('due_at')->comment('Calcolata via codice - scadenza finale');
            $table->dateTime('completed_at')->nullable()->comment('Quando il task è stato completato');

            $table->enum('status', ['active', 'warning', 'breached', 'completed'])->default('active')->comment('Stato attuale della deadline');
            $table->timestamps();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_deadlines');
    }
};
