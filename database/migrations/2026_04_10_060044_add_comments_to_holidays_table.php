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
        Schema::table('holidays', function (Blueprint $table) {
            $table->tinyInteger('is_recurring')->default(1)->comment('Se 1, la festività si ripete ogni anno (es. 25 Dicembre, 1 Maggio)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->tinyInteger('is_recurring')->default(1)->change();
        });
    }
};
