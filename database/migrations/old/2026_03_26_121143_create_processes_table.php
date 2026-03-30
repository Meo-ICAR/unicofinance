<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->foreignId('owner_function_id')->nullable()->constrained('business_functions')->nullOnDelete();

            $table->string('name')->comment('Nome del processo');
            $table->text('description')->nullable()->comment('Descrizione del processo');
            $table->string('target_model')->nullable()->comment('Modello target del processo');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
