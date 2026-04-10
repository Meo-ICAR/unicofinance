<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('process_task_privacy_data', function (Blueprint $table) {
            $table->comment('Mappatura (Matrice) tra i task operativi e le tipologie di dati privacy trattati. Base per il Registro dei Trattamenti.');
            $table->enum('access_level', ['read', 'write', 'update', 'delete'])->default('read')->comment('Operazione CRUD eseguita dal task sul dato personale (Art. 4 GDPR)')->change();
            $table->tinyInteger('is_encrypted')->default(0)->comment('Misura di sicurezza: il dato è crittografato at-rest o in-transit in questa fase?')->change();
            $table->tinyInteger('is_shared_externally')->default(0)->comment('Il task prevede la trasmissione del dato a responsabili esterni?')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_task_privacy_data', function (Blueprint $table) {
            $table->comment('');
            $table->enum('access_level', ['read', 'write', 'update', 'delete'])->default('read')->change();
            $table->tinyInteger('is_encrypted')->default(0)->change();
            $table->tinyInteger('is_shared_externally')->default(0)->change();
        });
    }
};
