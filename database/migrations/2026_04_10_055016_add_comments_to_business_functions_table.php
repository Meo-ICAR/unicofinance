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
        Schema::table('business_functions', function (Blueprint $table) {
            $table->string('code', 255)->comment('Codice identificativo univoco (es. HR, IT, SALES) per riferimento diretto nel codice')->change();
            $table->string('outsourcable_status', 255)->default('no')->comment('Indica se il processo/funzione è esternalizzato a terzi (Data Processor esterno)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_functions', function (Blueprint $table) {
            $table->string('code', 255)->change();
            $table->string('outsourcable_status', 255)->default('no')->change();
        });
    }
};
