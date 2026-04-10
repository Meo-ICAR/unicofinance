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
        Schema::table('company_branches', function (Blueprint $table) {
            $table->tinyInteger('is_main_office')->default(0)->comment('Indica se la filiale è la Sede Legale/Operativa principale')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_branches', function (Blueprint $table) {
            $table->tinyInteger('is_main_office')->default(0)->change();
        });
    }
};
