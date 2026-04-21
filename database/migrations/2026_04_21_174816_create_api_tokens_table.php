<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API Tokens — machine-to-machine authentication.
     *
     * Used by external Laravel applications on separate databases (same server)
     * to trigger BPM process execution (TaskExecution + TaskExecutionChecklistItem)
     * on behalf of a specific Company (tenant).
     */
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();

            // The tenant this token belongs to.
            // companies.id is char(36) UUID — must match exactly.
            $table->char('company_id', 36);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            // Human-readable name for auditing
            $table->string('name');

            // Hashed token — only the plain-text is shown once at creation
            $table->string('token', 64)->unique();

            // Optional: the calling application identifier (e.g. "mediazione_app")
            $table->string('caller_app')->nullable();

            // Scopes: JSON array of allowed abilities, e.g. ["bpm:create_execution"]
            $table->json('abilities')->nullable();

            // Rate/expiry controls
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
