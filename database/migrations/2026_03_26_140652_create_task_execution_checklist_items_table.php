<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_execution_checklist_items', function (Blueprint $table) {
            $table->id();

            // Relazione col Task in esecuzione
            $table->foreignId('task_execution_id')->constrained()->cascadeOnDelete();

            // Riferimento storico al template (nullable per evitare rotture se l'admin cancella il template)
            $table->foreignId('checklist_item_id')->nullable()->constrained()->nullOnDelete();

            // Copia della descrizione per storicizzazione (immutabile)
            $table->string('description');

            // --- STATO DI AVANZAMENTO ---
            $table
                ->boolean('is_checked')
                ->default(false)
                ->comment('Operazione completata con successo');

            // --- GESTIONE ECCEZIONI E SOLLECITI ---
            $table
                ->boolean('requires_revision')
                ->default(false)
                ->comment("True se l'operatore ha segnalato un problema (es. doc scaduto)");

            $table
                ->text('rejection_reason')
                ->nullable()
                ->comment('Testo del sollecito inviato al cliente/dipendente');

            // --- AUDIT TRAIL E COMPLIANCE ---
            $table
                ->boolean('is_not_applicable')
                ->default(false)
                ->comment('True se scartata dal Rule Engine (skip_condition o !require_condition)');

            $table
                ->boolean('automated_by_system')
                ->default(false)
                ->comment("True se completata dall'AI, Observer o Action senza intervento umano");

            // Ordine visivo
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_execution_checklist_items');
    }
};
