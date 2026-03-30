<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('es: "Approvazione Standard"');
            $table->string('process_type')->comment('es: "purchase_order"');
            $table->integer('duration_minutes')->comment('tempo totale concesso');
            $table->integer('warning_threshold_minutes')->comment('quanto prima avvisare');
            $table->boolean('exclude_weekends')->default(true)->comment('escludi weekend dal calcolo');
            $table->timestamps();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
