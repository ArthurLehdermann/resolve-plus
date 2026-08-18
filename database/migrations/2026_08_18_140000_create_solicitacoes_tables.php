<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitacoes')) {
            Schema::create('solicitacoes', function (Blueprint $table) {
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
        } else {
            Schema::table('solicitacoes', function (Blueprint $table): void {
                if (! Schema::hasColumn('solicitacoes', 'categoria_id')) {
                    $table->foreignUuid('categoria_id')->nullable()->constrained('categorias')->restrictOnDelete();
                }
                if (! Schema::hasColumn('solicitacoes', 'descricao')) {
                    $table->text('descricao')->nullable();
                }
                if (! Schema::hasColumn('solicitacoes', 'escopo')) {
                    $table->jsonb('escopo')->nullable();
                }
                if (! Schema::hasColumn('solicitacoes', 'data_desejada')) {
                    $table->date('data_desejada')->nullable();
                }
                if (! Schema::hasColumn('solicitacoes', 'faixa_preco_min')) {
                    $table->integer('faixa_preco_min')->nullable();
                }
                if (! Schema::hasColumn('solicitacoes', 'faixa_preco_max')) {
                    $table->integer('faixa_preco_max')->nullable();
                }
                if (! Schema::hasColumn('solicitacoes', 'faixa_preco_fator_bp')) {
                    $table->integer('faixa_preco_fator_bp')->nullable();
                }
                if (! Schema::hasColumn('solicitacoes', 'tabela_preco_id')) {
                    $table->uuid('tabela_preco_id')->nullable()->index();
                }
            });
        }

        if (! Schema::hasTable('fotos_solicitacao')) {
            Schema::create('fotos_solicitacao', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('solicitacao_id')->constrained('solicitacoes')->cascadeOnDelete();
                $table->string('url');
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamp('criado_em');
                $table->timestamp('atualizado_em');

                $table->index('solicitacao_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_solicitacao');
    }
};
