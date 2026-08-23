<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('titulo');
            $table->text('corpo');
            // Alvo para a tela abrir: {"solicitacao_id": "..."} ou {"servico_id": "..."}.
            $table->json('dados')->nullable();
            $table->timestamp('lida_em')->nullable();
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            // O feed é sempre "as minhas, mais recentes primeiro".
            $table->index(['usuario_id', 'criado_em']);
            // Contagem de não lidas é a consulta que roda em toda abertura de tela.
            $table->index(['usuario_id', 'lida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
