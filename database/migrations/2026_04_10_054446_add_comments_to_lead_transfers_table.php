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
        Schema::table('lead_transfers', function (Blueprint $table) {
            $table->comment('Registro delle compravendite o cessioni di anagrafiche (Lead) tra la company e partner terzi');
            $table->enum('transfer_method', ['api_tls', 'sftp', 'encrypted_csv'])->default('api_tls')->comment('Metodo tecnico di trasferimento sicuro del lead')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_transfers', function (Blueprint $table) {
            $table->comment('');
            $table->enum('transfer_method', ['api_tls', 'sftp', 'encrypted_csv'])->default('api_tls')->change();
        });
    }
};
