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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('oam', 100)->nullable()->comment('Numero Iscrizione OAM (Organismo Agenti e Mediatori Finanziari)')->change();
            $table->string('ivass', 100)->nullable()->comment('Numero Iscrizione IVASS (Istituto Vigilanza Assicurazioni)')->change();
            $table->string('numero_iscrizione_rui', 50)->nullable()->comment('Numero RUI (Registro Unico Intermediari Assicurativi)')->change();
            $table->string('employee_types', 255)->default('dipendente')->comment('Tipologia contrattuale (es. dipendente, collaboratore_piva, segnalatore)')->change();
            $table->tinyInteger('is_ghost')->default(0)->comment('Utenza tecnica di sistema, non associata a una persona fisica')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('oam', 100)->nullable()->change();
            $table->string('ivass', 100)->nullable()->change();
            $table->string('numero_iscrizione_rui', 50)->nullable()->change();
            $table->string('employee_types', 255)->default('dipendente')->change();
            $table->tinyInteger('is_ghost')->default(0)->change();
        });
    }
};
