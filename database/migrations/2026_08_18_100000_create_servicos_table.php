<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('servicos')) {
            Schema::table('servicos', function (Blueprint $table): void {
                if (! Schema::hasColumn('servicos', 'cliente_id')) {
                    $table->foreignUuid('cliente_id')->nullable()->constrained('usuarios')->restrictOnDelete();
                }

                if (! Schema::hasColumn('servicos', 'profissional_id')) {
                    $table->foreignUuid('profissional_id')->nullable()->constrained('usuarios')->restrictOnDelete();
                }

                if (! Schema::hasColumn('servicos', 'asaas_wallet_id')) {
                    $table->string('asaas_wallet_id')->nullable();
                }
            });

            return;
        }

        Schema::create('servicos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('profissional_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->string('asaas_wallet_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('servicos')) {
            return;
        }

        if (Schema::hasColumn('servicos', 'proposta_id')) {
            Schema::table('servicos', function (Blueprint $table): void {
                if (Schema::hasColumn('servicos', 'asaas_wallet_id')) {
                    $table->dropColumn('asaas_wallet_id');
                }
                if (Schema::hasColumn('servicos', 'cliente_id')) {
                    $table->dropConstrainedForeignId('cliente_id');
                }
                if (Schema::hasColumn('servicos', 'profissional_id')) {
                    $table->dropConstrainedForeignId('profissional_id');
                }
            });

            return;
        }

        Schema::dropIfExists('servicos');
    }
};
