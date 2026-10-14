<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Il registro sta sul database core, condiviso fra le app Unico. */
    public function getConnection()
    {
        return config('unico-core.connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable('api_calls')) {
            return;
        }

        $schema->create('api_calls', function (Blueprint $table) {
            $table->comment('Registro delle chiamate a web service esterni (Cerved, sanctions.io, Openapi) con esito e costo.');
            $table->id();
            $table->string('provider', 30)->index();
            $table->string('operation', 60);
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable()->comment('uuid o id numerico del record interrogato');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('cost', 10, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->json('request')->nullable()->comment('parametri inviati, senza credenziali');
            $table->json('response')->nullable()->comment('risposta del provider');
            $table->string('summary')->nullable()->comment('esito in sintesi');
            $table->string('reference')->nullable()->comment('id della ricerca presso il provider');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('api_calls');
    }
};
