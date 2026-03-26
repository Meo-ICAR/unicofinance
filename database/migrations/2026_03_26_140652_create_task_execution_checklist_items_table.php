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
            $table->foreignId('task_execution_id')->constrained('task_executions')->cascadeOnDelete();

            // Puntiamo all'elemento originale per leggerne l'istruzione ("Accendi il PC", ecc.)
            $table->foreignId('checklist_item_id')->constrained('checklist_items')->cascadeOnDelete();

            // I campi operativi
            $table->boolean('is_checked')->default(false);
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_execution_checklist_items');
    }
};
