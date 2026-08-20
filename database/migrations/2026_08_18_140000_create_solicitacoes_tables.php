<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignUuid('property_id')->constrained('properties')->restrictOnDelete();
            $table->text('descricao');
            $table->jsonb('escopo');
            $table->string('status', 30);
            $table->date('data_desejada')->nullable();
            $table->integer('faixa_preco_min')->nullable();
            $table->integer('faixa_preco_max')->nullable();
            $table->integer('faixa_preco_fator_bp')->nullable();
            $table->uuid('tabela_preco_id')->nullable()->index();
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            $table->index('cliente_id');
            $table->index('categoria_id');
            $table->index('property_id');
            $table->index('status');
            $table->index('criado_em');
        });

        Schema::create('fotos_solicitacao', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('solicitacao_id')->constrained('solicitacoes')->cascadeOnDelete();
            $table->string('url');
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            $table->index('solicitacao_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_solicitacao');
        Schema::dropIfExists('solicitacoes');
    }
};
