<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('es: "Natale", "Santo Patrono"');
            $table->date('holiday_date')->comment('Data specifica della festività');
            $table->boolean('is_recurring')->default(true)->comment('Se si ripete ogni anno');
            $table->timestamps();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
