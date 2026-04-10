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
        Schema::table('lead_return_logs', function (Blueprint $table) {
            $table->comment('Registro dei lead scartati o resi dai partner (es. numeri inesistenti o opt-out)');
            $table->enum('status', ['bounce', 'opt_out_requested', 'converted'])->default('bounce')->comment('Motivazione del reso: bounce (dati errati), opt_out (rifiuto privacy)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_return_logs', function (Blueprint $table) {
            $table->comment('');
            $table->enum('status', ['bounce', 'opt_out_requested', 'converted'])->default('bounce')->change();
        });
    }
};
