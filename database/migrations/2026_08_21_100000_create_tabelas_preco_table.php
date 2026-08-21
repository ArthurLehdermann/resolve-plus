<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabelas_preco', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->string('cidade');
            $table->integer('valor_min');
            $table->integer('valor_max');
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            $table->index('categoria_id');
            $table->index('cidade');
        });

        // No máximo uma linha ativa por par categoria+cidade (04-modelo-dados.md §TabelaPreco).
        DB::statement(
            'CREATE UNIQUE INDEX tabelas_preco_categoria_cidade_ativo_unique '.
            'ON tabelas_preco (categoria_id, cidade) WHERE ativo = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tabelas_preco');
    }
};
