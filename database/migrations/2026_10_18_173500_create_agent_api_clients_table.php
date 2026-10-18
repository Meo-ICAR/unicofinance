<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_api_clients', function (Blueprint $table) {
            $table->comment('Clienti dell\'API in ingresso per agenti (unicoagent): il token si conserva solo come hash, il segreto firma le richieste.');
            $table->id();
            $table->string('name')->unique()->comment('Nome del client, ad es. unicoagent');
            $table->string('token_hash', 64)->unique()->comment('SHA-256 del token Bearer');
            $table->text('secret')->comment('Segreto HMAC, cifrato');
            $table->timestamp('last_used_at')->nullable()->comment('Ultima richiesta accettata');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_api_clients');
    }
};
