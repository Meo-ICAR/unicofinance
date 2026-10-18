<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages')) {
            return;
        }

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id()->comment('ID intero autoincrementante');
            $table->foreignId('company_id')->nullable()->comment('Azienda (tenant) proprietaria del record');
            $table->foreignId('user_id')->nullable()->comment('Utente collegato al record');
            $table->string('thread_id')->comment('Conversazione a cui appartiene il messaggio');
            $table->string('role')->comment('Autore del messaggio (user, assistant)');
            $table->json('content')->nullable()->comment('Testo del messaggio');
            $table->json('meta')->nullable()->comment('Dati aggiuntivi del messaggio (JSON)');
            $table->timestamp('created_at')->nullable()->comment('Data e ora di creazione del record');
            $table->timestamp('updated_at')->nullable()->comment('Data e ora dell\'ultima modifica del record');
            $table->index(['thread_id', 'id'], 'chat_messages_thread_id_id_index');
            $table->index(['thread_id'], 'chat_messages_thread_id_index');
            $table->comment('Messaggi delle conversazioni con l\'assistente AI (NeuronAI), sul database di unicofinance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
