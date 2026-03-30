<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Nome tipo cliente (es. Privato, PMI, PA)');
            $table->string('description')->nullable()->comment('Descrizione del tipo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_types');
    }
};
