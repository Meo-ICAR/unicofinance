<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_function_employee', function (Blueprint $table) {
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('is_manager')->default(false)->comment('Indica se è il responsabile della funzione');
            $table->date('start_date')->nullable()->comment('Inizio incarico/contratto');
            $table->date('end_date')->nullable()->comment('Fine incarico (Scadenza)');
            $table->string('temporary_reason')->nullable()->comment('Causale incarico temporaneo o a progetto');

            $table->primary(['business_function_id', 'employee_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_function_employee');
    }
};
