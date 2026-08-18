<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_authorizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('servico_id')->constrained('servicos')->restrictOnDelete();
            $table->unsignedInteger('valor');
            $table->string('metodo', 20);
            $table->string('status', 20)->index();
            $table->timestamp('criado_em');
            $table->timestamp('expira_em')->nullable()->index();
        });

        DB::statement(
            "CREATE UNIQUE INDEX payment_authorizations_servico_autorizado_unique ON payment_authorizations (servico_id) WHERE status = 'AUTORIZADO'"
        );

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_authorization_id')->constrained('payment_authorizations')->restrictOnDelete();
            $table->string('tipo', 20)->index();
            $table->json('payload');
            $table->timestamp('criado_em')->index();
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_event_id')->constrained('payment_events')->restrictOnDelete();
            $table->unsignedInteger('valor');
            $table->text('motivo');
            $table->timestamp('criado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_authorizations');
    }
};
