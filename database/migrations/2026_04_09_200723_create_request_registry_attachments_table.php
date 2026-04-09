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
        Schema::create('request_registry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registry_id')->constrained('request_registries')->cascadeOnDelete();
            $table->string('file_path');
            $table->enum('file_type', [
                'richiesta',
                'documento_identita',
                'procura_mandato',
                'risposta',
                'documentazione_interna',
            ])->default('richiesta');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_registry_attachments');
    }
};
