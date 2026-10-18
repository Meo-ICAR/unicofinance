<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->after('pratica_prefix')
                ->comment('Indirizzo dell\'app a cui si avvisano documenti respinti o richiesti sulle sue pratiche (firmato con il segreto)');
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', fn (Blueprint $table) => $table->dropColumn('webhook_url'));
    }
};
