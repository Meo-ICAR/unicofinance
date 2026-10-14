<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_quotes', function (Blueprint $table) {
            $table->comment('Preventivi della simulazione agenti. Il progressivo è per produttore.');
            $table->id()->comment('ID intero autoincrementante');
            $table->unsignedBigInteger('fornitore_id')->comment('Produttore (fornitore sul database core) a cui appartiene il preventivo');
            $table->unsignedInteger('number')->comment('Progressivo del preventivo per quel produttore');
            $table->string('contract_type', 30)->comment('Tipo contratto della simulazione');
            $table->char('sex', 1)->comment('Sesso del cliente profilato');
            $table->date('birth_date')->comment('Data di nascita');
            $table->string('employment_relationship', 40)->comment('Tipo amministrazione');
            $table->date('hired_at')->nullable()->comment('Data assunzione, assente per i pensionati');
            $table->date('starts_at')->comment('Data decorrenza');
            $table->unsignedSmallInteger('duration_months')->comment('Durata in mesi');
            $table->decimal('assignable_quota', 12, 2)->nullable()->comment('Quota cedibile, per cessione e delega');
            $table->decimal('requested_amount', 12, 2)->nullable()->comment('Importo richiesto, per prestito e mutuo');
            $table->unsignedTinyInteger('age_at_start')->comment('Età alla decorrenza');
            $table->unsignedSmallInteger('seniority_months')->nullable()->comment('Anzianità di servizio in mesi alla decorrenza');
            $table->decimal('disbursed_min', 12, 2)->comment('Importo erogato minimo restituito dal preventivatore');
            $table->decimal('disbursed_max', 12, 2)->comment('Importo erogato massimo restituito dal preventivatore');
            $table->string('status', 20)->comment('salvato oppure perfezionato');
            $table->unsignedBigInteger('pratica_id')->nullable()->comment('Pratica core creata se il preventivo è stato perfezionato');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Utente core che ha concluso la simulazione');
            $table->timestamps();

            $table->unique(['fornitore_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_quotes');
    }
};
