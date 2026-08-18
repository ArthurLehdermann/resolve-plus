<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table): void {
            if (! Schema::hasColumn('servicos', 'cliente_id')) {
                $table->uuid('cliente_id')->nullable();
            }

            if (! Schema::hasColumn('servicos', 'profissional_id')) {
                $table->uuid('profissional_id')->nullable();
            }

            if (! Schema::hasColumn('servicos', 'asaas_wallet_id')) {
                $table->string('asaas_wallet_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        $drops = array_values(array_filter([
            Schema::hasColumn('servicos', 'asaas_wallet_id') ? 'asaas_wallet_id' : null,
            Schema::hasColumn('servicos', 'cliente_id') ? 'cliente_id' : null,
            Schema::hasColumn('servicos', 'profissional_id') ? 'profissional_id' : null,
        ]));

        if ($drops === []) {
            return;
        }

        Schema::table('servicos', function (Blueprint $table) use ($drops): void {
            $table->dropColumn($drops);
        });
    }
};
