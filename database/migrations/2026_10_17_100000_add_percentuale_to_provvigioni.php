<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `provvigioni` sta nel database di Proforma: la percentuale del compenso si aggiunge lì, se manca. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('proforma')->hasColumn('provvigioni', 'percentuale')) {
            return;
        }

        Schema::connection('proforma')->table('provvigioni', function (Blueprint $table) {
            $table->decimal('percentuale', 7, 4)->nullable()->after('importo')->comment('Percentuale del compenso: per il compenso dell\'istituto sulla base del prodotto (erogato, interessi...), per quello dell\'agente sulle provvigioni attive della pratica');
        });
    }

    public function down(): void
    {
        if (Schema::connection('proforma')->hasColumn('provvigioni', 'percentuale')) {
            Schema::connection('proforma')->table('provvigioni', fn (Blueprint $table) => $table->dropColumn('percentuale'));
        }
    }
};
