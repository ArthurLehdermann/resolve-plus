<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('propostas', 'observacoes_original')) {
            Schema::table('propostas', function (Blueprint $table): void {
                $table->text('observacoes_original')->nullable();
            });
        }

        if (! Schema::hasColumn('mensagens', 'texto_original')) {
            Schema::table('mensagens', function (Blueprint $table): void {
                $table->text('texto_original')->nullable();
            });
        }

        Schema::create('contact_leak_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('origem', 20);
            $table->foreignUuid('proposta_id')->nullable()->constrained('propostas')->restrictOnDelete();
            $table->foreignUuid('servico_id')->nullable()->constrained('servicos')->restrictOnDelete();
            $table->string('padrao_detectado', 20);
            $table->text('texto_original');
            $table->text('texto_filtrado');
            $table->timestamps();

            $table->index(['usuario_id', 'created_at']);
            $table->index('origem');
            $table->index('padrao_detectado');
        });

        Schema::create('contact_penalty_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->unsignedSmallInteger('attempts_in_window');
            $table->text('nota');
            $table->timestamps();

            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_penalty_notes');
        Schema::dropIfExists('contact_leak_attempts');

        if (Schema::hasColumn('mensagens', 'texto_original')) {
            Schema::table('mensagens', function (Blueprint $table): void {
                $table->dropColumn('texto_original');
            });
        }

        if (Schema::hasColumn('propostas', 'observacoes_original')) {
            Schema::table('propostas', function (Blueprint $table): void {
                $table->dropColumn('observacoes_original');
            });
        }
    }
};
