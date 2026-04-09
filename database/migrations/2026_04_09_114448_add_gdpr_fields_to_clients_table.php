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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('roc_registration_number')->nullable()->after('vat_number');
            $table->string('dpo_email')->nullable()->after('email');
            $table->string('privacy_policy_url')->nullable()->after('dpo_email');
            $table->timestamp('contract_signed_at')->nullable()->after('privacy_policy_url');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['roc_registration_number', 'dpo_email', 'privacy_policy_url', 'contract_signed_at']);
        });
    }
};
