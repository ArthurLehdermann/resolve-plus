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
            Schema::create('configuracoes', function (Blueprint $table) {
                $table->string('chave')->primary();
                $table->text('valor');
                $table->timestamp('atualizado_em');
            });
        }

        if (DB::table('configuracoes')->where('chave', 'COMISSAO_PERCENT')->doesntExist()) {
            $payload = ['chave' => 'COMISSAO_PERCENT', 'valor' => '10'];
            if (Schema::hasColumn('configuracoes', 'atualizado_em')) {
                $payload['atualizado_em'] = now();
            }
            if (Schema::hasColumn('configuracoes', 'created_at')) {
                $payload['created_at'] = now();
                $payload['updated_at'] = now();
            }
            DB::table('configuracoes')->insert($payload);
        }

        if (! Schema::hasTable('auditorias')) {
            Schema::create('auditorias', function (Blueprint $table) {
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

        if (! Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete();
                $table->string('chave', 80);
                $table->string('escopo', 180);
                $table->unsignedSmallInteger('response_status');
                $table->json('response_body');
                $table->timestamp('criado_em');

                $table->unique(['usuario_id', 'chave', 'escopo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('configuracoes');
    }
};
