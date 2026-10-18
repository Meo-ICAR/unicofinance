<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ogni app vede solo le pratiche che ha creato: il codice pratica comincia con il suo prefisso. unicoagent tiene «WA-», le altre
 * app già registrate ricevono «API{id}-».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->string('pratica_prefix', 20)->nullable()->unique()->after('name')
                ->comment('Prefisso del codice delle pratiche create dall\'app: l\'app raggiunge solo quelle');
        });

        DB::table('api_clients')->orderBy('id')->get(['id', 'name'])->each(function (object $client): void {
            DB::table('api_clients')->where('id', $client->id)->update(['pratica_prefix' => $client->name === 'unicoagent' ? 'WA-' : "API{$client->id}-"]);
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', fn (Blueprint $table) => $table->dropColumn('pratica_prefix'));
    }
};
