<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('servico_id')->constrained('servicos')->restrictOnDelete();
            $table->foreignUuid('autor_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('alvo_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('direcao', 40);
            $table->unsignedTinyInteger('nota');
            $table->text('comentario')->nullable();
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            $table->unique(['servico_id', 'direcao']);
            $table->index('alvo_id');
            $table->index('autor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes');
    }
};
