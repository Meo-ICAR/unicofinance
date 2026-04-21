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
        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('last_number')->default(0);
            $table->integer('n_scheduled')->default(0);
            $table->integer('n_progress')->default(0);
            $table->integer('n_done')->default(0);
            $table->integer('from');
            $table->integer('to');
            $table->date('date');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};
