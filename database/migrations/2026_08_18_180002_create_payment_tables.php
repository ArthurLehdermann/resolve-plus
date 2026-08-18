<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('servico_id')->constrained('servicos')->restrictOnDelete();
            $table->unsignedInteger('valor');
            $table->string('metodo', 20);
            $table->string('status', 20)->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->text('credit_card_token')->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->string('wallet_id')->nullable();
            $table->timestamp('criado_em');
            $table->timestamp('expira_em')->nullable()->index();
        });

        DB::statement(
            "CREATE UNIQUE INDEX payment_authorizations_servico_autorizado_unique ON payment_authorizations (servico_id) WHERE status = 'AUTORIZADO'"
        );

        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_authorization_id')->constrained('payment_authorizations')->restrictOnDelete();
            $table->string('tipo', 20)->index();
            $table->json('payload');
            $table->timestamp('criado_em')->index();
        });

        $this->createAppendOnlyTriggers();

        Schema::create('payment_splits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_event_id')->unique()->constrained('payment_events')->restrictOnDelete();
            $table->unsignedInteger('valor_profissional');
            $table->unsignedInteger('valor_plataforma');
            $table->decimal('aliquota_vigente', 7, 4);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_event_id')->constrained('payment_events')->restrictOnDelete();
            $table->unsignedInteger('valor');
            $table->text('motivo');
            $table->timestamp('criado_em');
        });

        if (! Schema::hasTable('payment_disputes')) {
            Schema::create('payment_disputes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('servico_id')->constrained('servicos')->restrictOnDelete();
                $table->string('tipo', 40);
                $table->string('status', 20)->index();
                $table->text('motivo')->nullable();
                $table->timestamp('aberta_em');
                $table->timestamp('resolvida_em')->nullable();
                $table->foreignUuid('resolvida_por_id')->nullable()->constrained('usuarios')->restrictOnDelete();
                $table->string('resultado', 20)->nullable();
                $table->text('justificativa')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_splits');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_authorizations');
    }

    private function createAppendOnlyTriggers(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION payment_events_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'INV-040: PaymentEvent is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER payment_events_no_update
                    BEFORE UPDATE ON payment_events
                    FOR EACH ROW EXECUTE PROCEDURE payment_events_append_only();

                CREATE TRIGGER payment_events_no_delete
                    BEFORE DELETE ON payment_events
                    FOR EACH ROW EXECUTE PROCEDURE payment_events_append_only();
                SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER payment_events_no_update BEFORE UPDATE ON payment_events
            BEGIN
                SELECT RAISE(ABORT, 'INV-040: PaymentEvent is append-only');
            END;

            CREATE TRIGGER payment_events_no_delete BEFORE DELETE ON payment_events
            BEGIN
                SELECT RAISE(ABORT, 'INV-040: PaymentEvent is append-only');
            END;
            SQL);
    }
};
