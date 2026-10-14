<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornitori_listini_provvigionali', function (Blueprint $table) {
            $table->boolean('predefinito')->default(false)->after('listino_provvigionale_id')
                ->comment('Listino predefinito del fornitore (uno solo)');
        });
    }

    public function down(): void
    {
        Schema::table('fornitori_listini_provvigionali', function (Blueprint $table) {
            $table->dropColumn('predefinito');
        });
    }
};
