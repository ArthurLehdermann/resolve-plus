<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->uuid('servico_id')->nullable()->index();
            $table->timestamp('data');
            $table->string('categoria');
            $table->text('resumo');
            $table->string('origem', 20)->index();
            $table->string('confiabilidade', 20);
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
