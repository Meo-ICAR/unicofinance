<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();

            // Relazione con il gruppo genitore
            $table
                ->foreignId('checklist_id')
                ->constrained('checklists')
                ->cascadeOnDelete()
                ->comment('Relazione con il gruppo (es. Controlli Preliminari).');

            // Il testo della regola
            $table
                ->text('instruction')
                ->comment("SNAPSHOT SOURCE: L'operazione dettagliata da compiere. Questo testo DEVE essere clonato nella \"task_execution_checklist_items\" al momento dell'assegnazione per garantire l'immutabilità storica.");

            // Flag di base
            $table
                ->boolean('is_mandatory')
                ->default(true)
                ->comment('Indica se questo step è obbligatorio di default per poter completare il task associato.');

            // Motore a Regole (Rule Engine)
            $table
                ->string('require_condition_class')
                ->nullable()
                ->comment("RULE ENGINE: Namespace completo (es. App\Rules\ForeignerRule) della classe PHP che valuta l'anagrafica. Se restituisce true, questa voce diventa obbligatoria ignorando il flag is_mandatory.");

            $table
                ->string('skip_condition_class')
                ->nullable()
                ->comment("RULE ENGINE: Namespace completo (es. App\Rules\Under65Rule) della classe PHP. Se restituisce true, il sistema scarta questa voce automaticamente impostando is_not_applicable=true nell'esecuzione.");

            // Ordinamento UI
            $table
                ->unsignedInteger('sort_order')
                ->default(0)
                ->comment("Ordine di visualizzazione all'interno della UI di Filament.");

            // Timestamps e Compliance
            $table->timestamps();

            $table
                ->softDeletes()
                ->comment("COMPLIANCE REQUIREMENT: Non eliminare mai fisicamente le regole dal database. Se il master broker disattiva un controllo, questo va solo nascosto (Soft Delete) per non corrompere le FK dell'Audit Trail passato.");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
