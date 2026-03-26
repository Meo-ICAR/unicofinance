<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();

            $table->string('name')->comment('Nome della checklist (es. Controlli preliminari)');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
