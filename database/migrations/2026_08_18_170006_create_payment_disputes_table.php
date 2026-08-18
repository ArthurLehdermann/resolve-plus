<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_disputes')) {
            return;
        }

        Schema::create('payment_disputes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('servico_id')->constrained('servicos')->restrictOnDelete();
            $table->string('tipo', 40)->index();
            $table->string('status', 20)->index();
            $table->text('motivo')->nullable();
            $table->timestamp('aberta_em');
            $table->timestamp('resolvida_em')->nullable();
            $table->uuid('resolvida_por_id')->nullable();
            $table->string('resultado', 20)->nullable();
            $table->text('justificativa')->nullable();
            $table->timestamps();

            $table->index('servico_id');
        });

        // Uma disputa ABERTA do mesmo tipo por serviço (INV-045).
        DB::statement("CREATE UNIQUE INDEX payment_disputes_aberta_tipo_unique ON payment_disputes (servico_id, tipo) WHERE status = 'ABERTA'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
    }
};
