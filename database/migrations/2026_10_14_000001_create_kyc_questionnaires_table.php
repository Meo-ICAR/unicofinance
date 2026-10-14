<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Questionari KYC del richiedente. Il cliente sta sul database core: qui si tiene solo il questionario. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_questionnaires', function (Blueprint $table) {
            $table->comment('Questionari di adeguata verifica (KYC) compilati dal portale agenti.');
            $table->id();
            $table->unsignedBigInteger('client_id')->index()->comment('clients.id sul database core');
            $table->string('pratica_id', 64)->nullable()->comment('Pratica a cui si riferisce la compilazione');
            $table->string('pep_status')->nullable()->comment('Condizione PEP dichiarata');
            $table->string('financing_purpose')->nullable()->comment('Scopo del finanziamento');
            $table->string('risk_level')->nullable()->comment('Livello di rischio');
            $table->string('status')->default('draft')->index()->comment('draft, complete, approved');
            $table->dateTime('compiled_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('economic_activity')->nullable();
            $table->string('activity_sector')->nullable();
            $table->string('activity_location')->nullable();
            $table->string('financing_nature')->nullable();
            $table->string('income_band')->nullable();
            $table->string('wealth_band')->nullable();
            $table->boolean('acts_for_third_party')->default(false);
            $table->string('legal_nature')->nullable();
            $table->string('geographic_area')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_questionnaires');
    }
};
