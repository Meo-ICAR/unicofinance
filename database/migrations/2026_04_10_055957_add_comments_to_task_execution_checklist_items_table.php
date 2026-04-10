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
        Schema::table('task_execution_checklist_items', function (Blueprint $table) {
            $table->tinyInteger('automated_by_system')->default(0)->comment("Se 1, la spunta è stata messa da una Action Class (PHP) automatizzata e non dall'utente")->change();
            $table->tinyInteger('is_not_applicable')->default(0)->comment('Indica che il controllo è stato bypassato in modo legittimo (es. condizione skip verificata)')->change();
            $table->tinyInteger('requires_revision')->default(0)->comment('Flag per il workflow di approvazione (es. il manager ha bocciato questo specifico item operativo)')->change();
            $table->unsignedBigInteger('validated_by_employee_id')->nullable()->comment("Firma digitale interna: dipendente che ha validato l'azione (Accountability)")->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_execution_checklist_items', function (Blueprint $table) {
            $table->tinyInteger('automated_by_system')->default(0)->change();
            $table->tinyInteger('is_not_applicable')->default(0)->change();
            $table->tinyInteger('requires_revision')->default(0)->change();
            $table->unsignedBigInteger('validated_by_employee_id')->nullable()->change();
        });
    }
};
