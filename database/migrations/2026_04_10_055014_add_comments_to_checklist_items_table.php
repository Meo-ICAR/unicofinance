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
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->string('require_condition_class', 255)->nullable()->comment("FQN della classe PHP (Rule) valutata dinamicamente per decidere se l'item è obbligatorio nel contesto attuale")->change();
            $table->string('skip_condition_class', 255)->nullable()->comment("FQN della classe PHP che stabilisce se l'item può essere bypassato")->change();
            $table->string('action_class', 255)->nullable()->comment("FQN della classe PHP eseguita come side-effect quando l'item viene spuntato (es. invio email, cambio stato)")->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->string('require_condition_class', 255)->nullable()->change();
            $table->string('skip_condition_class', 255)->nullable()->change();
            $table->string('action_class', 255)->nullable()->change();
        });
    }
};
