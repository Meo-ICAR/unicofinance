<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_executions', function (Blueprint $table) {
            $table->id();
            // Il collegamento al "Template" del task
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();

            // Chi deve eseguire questo specifico task (chi ha la 'R' nel RACI al momento della creazione)
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();

            // Stato e tempistiche
            $table->enum('status', ['todo', 'in_progress', 'completed'])->default('todo');
            $table->date('due_date')->nullable()->comment('Scadenza per completare il task');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_executions');
    }
};
