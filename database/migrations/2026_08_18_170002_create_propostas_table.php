<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('propostas')) {
            return;
        }

        Schema::create('propostas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('solicitacao_id')->constrained('solicitacoes')->restrictOnDelete();
            $table->foreignUuid('profissional_id')->constrained('usuarios')->restrictOnDelete();
            $table->unsignedInteger('valor');
            $table->unsignedInteger('prazo_dias');
            $table->unsignedInteger('garantia_dias');
            $table->text('observacoes')->nullable();
            $table->string('status', 20)->index();
            $table->timestamps();

            $table->index('profissional_id');
            $table->index('solicitacao_id');
        });

        // INV-010: no máximo uma proposta ACEITA por solicitação (índice parcial).
        DB::statement("CREATE UNIQUE INDEX propostas_solicitacao_aceita_unique ON propostas (solicitacao_id) WHERE status = 'ACEITA'");
    }

    public function down(): void
    {
        Schema::dropIfExists('propostas');
    }
};
