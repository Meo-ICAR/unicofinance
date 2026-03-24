<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Aggiungiamo il Super Admin globale
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
        });

        // 2. Aggiungiamo il ruolo specifico per l'azienda nella pivot
        Schema::table('company_user', function (Blueprint $table) {
            $table->string('role')->default('user')->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_and_pivot', function (Blueprint $table) {
            //
        });
    }
};
