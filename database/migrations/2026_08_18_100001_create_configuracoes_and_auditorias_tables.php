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

        if (DB::table('configuracoes')->where('chave', 'COMISSAO_PERCENT')->doesntExist()) {
            $now = now();
            $payload = ['chave' => 'COMISSAO_PERCENT', 'valor' => '10'];

            if (Schema::hasColumn('configuracoes', 'atualizado_em')) {
                $payload['atualizado_em'] = $now;
            }

            if (Schema::hasColumn('configuracoes', 'created_at')) {
                $payload['created_at'] = $now;
                $payload['updated_at'] = $now;
            }

            DB::table('configuracoes')->insert($payload);
        }

        if (! Schema::hasTable('auditorias')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
