<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le migration di core creano già la tabella (database di prova, o database già popolato).
        if (Schema::hasTable('client_relations')) {
            return;
        }

        if (Schema::hasTable('client_relations')) {
            return;
        }

        Schema::create('client_relations', function (Blueprint $table) {
            $table->id()->comment('ID univoco del legame');
            $table->unsignedBigInteger('company_id')->index()->comment('Cliente persona giuridica (core clients.id)');
            $table->unsignedBigInteger('client_id')->index()->comment('Soggetto collegato (core clients.id)');
            $table->string('role', 30)->comment('legale_rappresentante | amministratore | socio | titolare_effettivo | garante | altro');
            $table->decimal('shares_percentage', 5, 2)->nullable()->comment('Percentuale quote possedute');
            $table->date('data_inizio_ruolo')->nullable();
            $table->date('data_fine_ruolo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'client_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_relations');
    }
};
