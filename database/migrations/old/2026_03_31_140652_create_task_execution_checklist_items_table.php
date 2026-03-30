<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_execution_checklist_items', function (Blueprint $table) {
            $table->id();

            // Relazione col Task in esecuzione
            $table->foreignId('task_execution_id')->constrained()->cascadeOnDelete();

            // Riferimento storico al template (nullable per evitare rotture se l'admin cancella il template)
            $table->foreignId('checklist_item_id')->nullable()->constrained()->nullOnDelete();

            // --- STATO DI AVANZAMENTO ---
            $table
                ->boolean('is_checked')
                ->default(false)
                ->comment('Operazione completata con successo');

            // --- AUDIT TRAIL E COMPLIANCE ---

            // Ordine visivo
            $table->integer('sort_order')->default(0);

            // 1. Snapshot Pattern (Immutabilità)
            $table
                ->text('description')
                ->nullable()
                ->comment("SNAPSHOT REQUIREMENT: Testo dell'istruzione clonato dal catalogo (checklist_items) al momento della generazione. Se il master broker modifica la regola madre mesi dopo, questa riga storica NON deve cambiare testo.");

            // 2. Rule Engine Flags (Automazioni)
            $table
                ->boolean('is_not_applicable')
                ->default(false)
                ->comment('RULE ENGINE: True se la voce è stata esclusa dal sistema o dichiarata non necessaria dall\'operatore (es. regola "Under 65" scattata).');

            $table
                ->boolean('automated_by_system')
                ->default(false)
                ->comment("AUDIT: True se questa riga è stata spuntata o esclusa in automatico da un algoritmo e non da un click umano. Essenziale per giustificare all'OAM l'assenza di firma operatore.");

            // 3. Ciclo di Revisione e Rifiuto
            $table
                ->boolean('requires_revision')
                ->default(false)
                ->comment("WORKFLOW: True se l'analista ha esaminato il documento allegato a questo item e lo ha scartato (es. sfocato, incompleto).");

            $table
                ->text('rejection_reason')
                ->nullable()
                ->comment("AUDIT: Motivazione testuale del rifiuto. Viene stampata nel PDF di conformità e mostrata all'agente per correggere l'errore.");

            // 4. Matrice RACI (Firma Elettronica Logica)
            $table
                ->foreignId('validated_by_employee_id')
                ->nullable()
                ->comment('RACI [R]: ID del dipendente che ha fisicamente cliccato su "Valida/Approva". Corrisponde alla firma digitale dell\'operatore su questa specifica azione.')
                ->constrained('employees')
                ->nullOnDelete();

            $table->timestamps();  // Crea created_at e updated_at

            // LA MAGIA DEL PACCHETTO:
            $table->userstamps();  // Crea in automatico created_by e updated_by

            // SE USI I SOFT DELETES:
            $table->softDeletes();
            $table->userstampSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_execution_checklist_items');
    }
};
