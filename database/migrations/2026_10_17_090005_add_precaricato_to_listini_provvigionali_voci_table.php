<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('listini_provvigionali_voci', 'precaricato')) {
            return;
        }

        Schema::table('listini_provvigionali_voci', function (Blueprint $table) {
            $table->decimal('precaricato', 3, 1)->default(0)->after('percentuale')->comment('Punti precaricati riconosciuti al produttore su questa voce');
        });
    }

    public function down(): void
    {
        Schema::table('listini_provvigionali_voci', function (Blueprint $table) {
            $table->dropColumn('precaricato');
        });
    }
};
