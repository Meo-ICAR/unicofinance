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
        Schema::create('process_request_mappings', function (Blueprint $table) {
            $table->id();
            $table->enum('request_type', ['accesso', 'cancellazione', 'rettifica', 'opposizione', 'limitazione', 'portabilita', 'revoca_consenso', 'reclamazione']);
            $table->foreignId('process_id')->constrained('processes')->onDelete('cascade');
            $table->tinyInteger('is_suggested')->default(0)->comment('Se 1, il sistema lo pre-seleziona come scelta consigliata');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_request_mappings');
    }
};
