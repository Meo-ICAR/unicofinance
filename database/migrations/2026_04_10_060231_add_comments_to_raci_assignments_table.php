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
        Schema::table('raci_assignments', function (Blueprint $table) {
            $table->enum('role', ['R', 'A', 'C', 'I'])->comment('Matrice organizzativa: Responsible (Esecutore), Accountable (Responsabile), Consulted (Consulente), Informed (Informato)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raci_assignments', function (Blueprint $table) {
            $table->enum('role', ['R', 'A', 'C', 'I'])->change();
        });
    }
};
