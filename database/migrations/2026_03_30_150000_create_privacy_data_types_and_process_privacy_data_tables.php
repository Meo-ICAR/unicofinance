<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mattiverse\Userstamps\Traits\Userstamps;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('privacy_data_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique()->comment('es. fin_bank');
            $table->string('name');
            $table->enum('category', ['comuni', 'particolari', 'giudiziari'])->comment('Categoria dati secondo GDPR');
            $table->integer('retention_years')->default(10)->comment('Periodo di conservazione standard');
            $table->timestamps();
            $table->userstamps();
        });

        Schema::create('process_task_privacy_data', function (Blueprint $table) {
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();
            $table->foreignId('privacy_data_type_id')->constrained('privacy_data_types')->cascadeOnDelete();
            $table->enum('access_level', ['read', 'write', 'delete'])->default('read');
            $table->timestamps();
            $table->userstamps();

            $table->primary(['process_task_id', 'privacy_data_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_task_privacy_data');
        Schema::dropIfExists('privacy_data_types');
    }
};
