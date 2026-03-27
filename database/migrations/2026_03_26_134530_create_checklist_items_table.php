<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();

            // Relazione col Task Template padre (es. "Fase 1: Onboarding")
            $table->foreignId('task_template_id')->constrained()->cascadeOnDelete();

            // L'istruzione per l'operatore (es. "Verifica Carta d'Identità")
            $table->string('description');

            // --- 1. EVENT-DRIVEN (Automazione Passiva) ---
            $table
                ->unsignedBigInteger('required_document_type_id')
                ->nullable()
                ->comment('Punta al DB dms_db.document_types. Spunta automatica se il doc arriva.');

            // --- 2. ACTION PATTERN (Automazione Attiva) ---
            $table
                ->string('action_class')
                ->nullable()
                ->comment("Es: App\Actions\ActivateUserAction. Scatta al click dell'operatore.");

            // --- 3. RULE ENGINE (Motore di Regole) ---
            $table
                ->string('skip_condition_class')
                ->nullable()
                ->comment('OPT-OUT: Salta questa voce se la classe restituisce true (es. HasHireDate).');

            $table
                ->string('require_condition_class')
                ->nullable()
                ->comment('OPT-IN: Richiedi questa voce SOLO SE la classe restituisce true (es. IsElderly).');

            // Ordine visivo
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
