<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dropping first because we are changing the PK from composite to id()
        Schema::dropIfExists('business_function_client');

        Schema::create('business_function_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            
            $table->date('start_date')->nullable()->comment('Inizio incarico/contratto');
            $table->date('end_date')->nullable()->comment('Fine incarico (Scadenza)');
            $table->string('temporary_reason')->nullable()->comment('Causale incarico temporaneo o a progetto');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_function_client');
        
        // Restore previous structure if needed
        Schema::create('business_function_client', function (Blueprint $table) {
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('contract_expiry_date')->nullable()->comment('Data scadenza contratto per questo consulente');
            $table->primary(['business_function_id', 'client_id']);
            $table->timestamps();
        });
    }
};
