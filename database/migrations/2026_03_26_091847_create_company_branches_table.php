<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_branches', function (Blueprint $table) {
            $table->id('id')->comment('ID univoco filiale');
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name')->comment('Nome della sede');
            $table->boolean('is_main_office')->default(false)->comment('Indica se questa è la sede legale/principale dell\'agenzia');
            $table->string('manager_first_name', 100)->nullable()->comment('Nome del referente/responsabile della sede');
            $table->string('manager_last_name', 100)->nullable()->comment('Cognome del referente/responsabile della sede');
            $table->string('manager_tax_code', 16)->nullable()->comment('Codice Fiscale del referente della sede');
            $table->timestamps();
        });

        Schema::table('company_branches', function (Blueprint $table) {
            $table->comment = 'Anagrafica delle sedi operative e legali delle società di mediazione con relativi referenti.';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_branches');
    }
};
