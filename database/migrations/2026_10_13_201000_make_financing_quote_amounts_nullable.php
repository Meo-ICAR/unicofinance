<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_quotes', function (Blueprint $table) {
            $table->decimal('disbursed_min', 12, 2)->nullable()->comment('Importo erogato minimo restituito dal preventivatore')->change();
            $table->decimal('disbursed_max', 12, 2)->nullable()->comment('Importo erogato massimo restituito dal preventivatore')->change();
        });
    }

    public function down(): void
    {
        Schema::table('financing_quotes', function (Blueprint $table) {
            $table->decimal('disbursed_min', 12, 2)->nullable(false)->comment('Importo erogato minimo restituito dal preventivatore')->change();
            $table->decimal('disbursed_max', 12, 2)->nullable(false)->comment('Importo erogato massimo restituito dal preventivatore')->change();
        });
    }
};
