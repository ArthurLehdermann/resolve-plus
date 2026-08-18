<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracoes')) {
            Schema::create('configuracoes', function (Blueprint $table): void {
                $table->string('chave', 80)->primary();
                $table->string('valor', 255);
                $table->timestamps();
            });
        }

        $now = now();
        $usesTimestamps = Schema::hasColumn('configuracoes', 'created_at');
        $usesAtualizado = Schema::hasColumn('configuracoes', 'atualizado_em');

        $defaults = [
            'AUTO_APPROVAL_HOURS' => '72',
            'CANCELLATION_PENALTY_TIER1_HOURS' => '48',
            'CANCELLATION_PENALTY_TIER1_PERCENT' => '10',
            'CANCELLATION_PENALTY_TIER2_HOURS' => '24',
            'CANCELLATION_PENALTY_TIER2_PERCENT' => '25',
            'CANCELLATION_PENALTY_TIER3_PERCENT' => '50',
            'DISPUTE_MEDIATION_DAYS' => '7',
        ];

        foreach ($defaults as $chave => $valor) {
            if ($usesTimestamps) {
                DB::table('configuracoes')->updateOrInsert(
                    ['chave' => $chave],
                    ['valor' => $valor, 'created_at' => $now, 'updated_at' => $now],
                );
            } elseif ($usesAtualizado) {
                DB::table('configuracoes')->updateOrInsert(
                    ['chave' => $chave],
                    ['valor' => $valor, 'atualizado_em' => $now],
                );
            } else {
                DB::table('configuracoes')->updateOrInsert(
                    ['chave' => $chave],
                    ['valor' => $valor],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
