<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_functions', function (Blueprint $table) {
            $table->id('id')->comment('ID univoco funzione business');
            $table->string('code')->unique()->comment('Codice identificativo univoco funzione');
            $table->string('macro_area')->comment('Macro area di appartenenza');
            $table->string('name')->comment('Nome specifico funzione business');
            $table->string('type')->comment('Tipologia funzione');
            $table->text('description')->nullable()->comment('Descrizione dettagliata funzione');
            $table->string('outsourcable_status')->default('no');
            $table->foreignId('managed_by_id')->nullable()->constrained('business_functions')->nullOnDelete()->comment('Riferimento alla funzione padre (Manager)');
            $table->longText('mission')->nullable()->comment('What does the function do');
            $table->longText('responsibility')->nullable()->comment('List of activities and responsibilities');
            $table->timestamps();
        });

        Schema::table('business_functions', function (Blueprint $table) {
            $table->comment = "Funzioni aziendali (Template Funzionogramma globale)";
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_functions');
    }
};
