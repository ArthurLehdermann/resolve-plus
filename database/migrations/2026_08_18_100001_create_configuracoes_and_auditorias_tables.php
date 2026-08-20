<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table): void {
            $table->string('chave', 80)->primary();
            $table->string('valor', 255);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            'COMISSAO_PERCENT' => '10',
            'AUTO_APPROVAL_HOURS' => '72',
            'CANCELLATION_PENALTY_TIER1_HOURS' => '48',
            'CANCELLATION_PENALTY_TIER1_PERCENT' => '10',
            'CANCELLATION_PENALTY_TIER2_HOURS' => '24',
            'CANCELLATION_PENALTY_TIER2_PERCENT' => '25',
            'CANCELLATION_PENALTY_TIER3_PERCENT' => '50',
            'DISPUTE_MEDIATION_DAYS' => '7',
        ];

        foreach ($defaults as $chave => $valor) {
            DB::table('configuracoes')->insert([
                'chave' => $chave,
                'valor' => $valor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('auditorias', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('acao', 80)->index();
            $table->string('entidade', 80);
            $table->uuid('id_entidade')->index();
            $table->timestamp('data');
            $table->string('ip', 45)->nullable();
            $table->text('justificativa')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('configuracoes');
    }
};
