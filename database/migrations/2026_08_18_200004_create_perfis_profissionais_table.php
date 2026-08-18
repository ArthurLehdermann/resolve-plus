<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis_profissionais', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->unique()->constrained('usuarios')->restrictOnDelete();
            $table->string('nivel_confianca', 20)->index();
            $table->unsignedInteger('servicos_aprovados')->default(0);
            $table->unsignedInteger('nota_media_dez')->nullable();
            $table->unsignedTinyInteger('taxa_cancelamento_pct')->default(0);
            $table->unsignedInteger('reclamacoes_12m')->default(0);
            $table->timestamp('nivel_atualizado_em');
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            $table->index(['nivel_confianca', 'nota_media_dez']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis_profissionais');
    }
};
