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
        Schema::create('request_registry_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registry_id')->constrained('request_registries')->cascadeOnDelete();
            $table->dateTime('action_date');
            $table->enum('action_type', [
                'assegnazione',
                'inoltro',
                'risposta_preliminare',
                'evasione',
                'estensione_termini',
                'reclamo_interno',
            ]);
            $table->text('description')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_registry_actions');
    }
};
