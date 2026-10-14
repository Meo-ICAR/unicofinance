<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Leasing, mutuo chirografario e microcredito sono per società: niente sesso, nascita e tipo amministrazione. */
    public function up(): void
    {
        Schema::table('financing_quotes', function (Blueprint $table) {
            $table->char('sex', 1)->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->string('employment_relationship', 40)->nullable()->change();
            $table->unsignedTinyInteger('age_at_start')->nullable()->change();
            $table->string('company_name', 191)->nullable()->after('employment_relationship')->comment('Ragione sociale, per i prodotti corporate');
            $table->string('vat_number', 20)->nullable()->after('company_name')->comment('Partita IVA, per i prodotti corporate');
        });
    }

    public function down(): void
    {
        Schema::table('financing_quotes', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'vat_number']);
        });
    }
};
