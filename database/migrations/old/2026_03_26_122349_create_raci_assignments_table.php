<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raci_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();

            $table->enum('role', ['R', 'A', 'C', 'I'])->comment('R=Responsible, A=Accountable, C=Consulted, I=Informed');

            $table->unique(['process_task_id', 'business_function_id'], 'unique_raci_task');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raci_assignments');
    }
};
