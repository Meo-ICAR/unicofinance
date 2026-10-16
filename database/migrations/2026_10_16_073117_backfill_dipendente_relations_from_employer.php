<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Ogni cliente con un datore di lavoro (`clients.employer_id`) è legato al datore con il ruolo `dipendente` in `client_relations`. */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection(config('unico-core.connection'))->table('clients')
            ->whereNotNull('employer_id')
            ->orderBy('id')
            ->select(['id', 'employer_id'])
            ->chunkById(500, function ($clients): void {
                foreach ($clients as $client) {
                    DB::table('client_relations')->insertOrIgnore([
                        'company_id' => $client->employer_id,
                        'client_id' => $client->id,
                        'role' => 'dipendente',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('client_relations')->where('role', 'dipendente')->delete();
    }
};
