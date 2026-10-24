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
        Schema::create('notifications', function (Blueprint $table) {
            $table->comment('Notifiche di Laravel salvate nel database per gli utenti del pannello');

            $table->uuid('id')->primary()->comment('Identificativo univoco della notifica');
            $table->string('type')->comment('Classe della notifica');
            $table->morphs('notifiable');
            $table->text('data')->comment('Contenuto della notifica, in JSON');
            $table->timestamp('read_at')->nullable()->comment('Data e ora di lettura');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
