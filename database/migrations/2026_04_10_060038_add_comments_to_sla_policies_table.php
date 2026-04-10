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
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->integer('warning_threshold_minutes')->comment("Minuti prima della scadenza in cui triggerare l'evento di Warning (es. per inviare una notifica Slack/Email al manager)")->change();
            $table->tinyInteger('exclude_weekends')->default(1)->comment('Se 1, il calcolo dei tempi deve usare i giorni lavorativi (Business Days) ignorando Sabato, Domenica e tabelle holidays')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->integer('warning_threshold_minutes')->change();
            $table->tinyInteger('exclude_weekends')->default(1)->change();
        });
    }
};
