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
        Schema::table('lead_transfers', function (Blueprint $table) {
            $table->enum('transfer_method', ['api_tls', 'sftp', 'encrypted_csv'])->default('api_tls')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('lead_transfers', function (Blueprint $table) {
            $table->dropColumn('transfer_method');
        });
    }
};
