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

        // Stesso schema delle app nuove (ApiClient::defaultPrefix): il nome in maiuscolo; se due nomi danno lo stesso prefisso,
        // il secondo prende l'id. WA- resta riservato a unicoagent.
        $used = ['WA-' => true];
        DB::table('api_clients')->orderBy('id')->get(['id', 'name'])->each(function (object $client) use (&$used): void {
            $prefix = $client->name === 'unicoagent' ? 'WA-' : substr(strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $client->name)), 0, 19).'-';
            if ($client->name !== 'unicoagent' && ($prefix === '-' || isset($used[$prefix]))) {
                $prefix = "API{$client->id}-";
            }
            $used[$prefix] = true;
            DB::table('api_clients')->where('id', $client->id)->update(['pratica_prefix' => $prefix]);
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', fn (Blueprint $table) => $table->dropColumn('pratica_prefix'));
    }
};
