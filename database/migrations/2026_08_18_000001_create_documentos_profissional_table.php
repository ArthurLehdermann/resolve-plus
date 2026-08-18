<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_profissional', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profissional_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo', 50);
            $table->string('arquivo');
            $table->string('status', 30);
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignUuid('revisado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_em')->nullable();
            $table->string('apolice_numero', 100)->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->timestamps();

            $table->index(['profissional_id', 'tipo']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_profissional');
    }
};
