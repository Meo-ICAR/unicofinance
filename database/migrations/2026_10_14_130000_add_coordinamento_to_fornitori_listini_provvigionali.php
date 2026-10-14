<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return config('unico-core.connection');
    }

    public function up(): void
    {
        Schema::table('fornitori_listini_provvigionali', function (Blueprint $table) {
            $table->boolean('coordinamento')->default(false)->after('listino_provvigionale_id')
                ->comment('Listino per le pratiche in cui il fornitore agisce da coordinatore');
            $table->index('fornitori_id', 'idx_fornitori_listini_fornitore');
            $table->index('listino_provvigionale_id', 'idx_fornitori_listini_listino');
            $table->dropUnique('uq_fornitore_listino');
            $table->unique(['fornitori_id', 'listino_provvigionale_id', 'coordinamento'], 'uq_fornitore_listino_ruolo');
        });
    }

    public function down(): void
    {
        Schema::table('fornitori_listini_provvigionali', function (Blueprint $table) {
            $table->dropUnique('uq_fornitore_listino_ruolo');
            $table->unique(['fornitori_id', 'listino_provvigionale_id'], 'uq_fornitore_listino');
            $table->dropIndex('idx_fornitori_listini_fornitore');
            $table->dropIndex('idx_fornitori_listini_listino');
            $table->dropColumn('coordinamento');
        });
    }
};
