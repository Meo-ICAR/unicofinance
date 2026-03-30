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

            $table->date('due_date')->nullable()->comment('Scadenza per completare il task');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table
                ->string('reference_number')
                ->nullable()
                ->comment("Protocollo univoco immutabile (es. PRT-2026-001). Fondamentale per la ricerca dell'ispettore e l'intestazione del Fascicolo PDF.");

            // 2. Integrazione con il Document Management System (DMS)
            $table
                ->string('audit_dms_id')
                ->nullable()
                ->comment('ID univoco restituito dal DMS esterno. Se questo campo è valorizzato, significa che il PDF di audit è stato generato e blindato in sola lettura (policy WORM).');

            // 3. Aggiornamento Enum dello Status
            // Nota: Cambiamo la colonna in stringa per maggiore flessibilità nei workflow futuri, mantenendo i vecchi stati.
            $table
                ->string('status')
                ->default('todo')
                ->comment('Stato avanzato: todo, in_progress, in_review (attesa back-office), completed, archived (chiusa e archiviata nel DMS).');

            $table
                ->softDeletes()
                ->comment('COMPLIANCE REQUIREMENT: I record bancari/finanziari non si cancellano MAI fisicamente. Se un utente elimina la pratica, viene solo nascosta.');
            $table->unsignedBigInteger('previous_task_execution_id')->nullable()->comment("ID dell'esecuzi");

            $table->timestamps();  // Crea created_at e updated_at

            // LA MAGIA DEL PACCHETTO:
            $table->userstamps();  // Crea in automatico created_by e updated_by

            // SE USI I SOFT DELETES:
            //    $table->softDeletes();
            $table->userstampSoftDeletes();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_executions');
    }
};
