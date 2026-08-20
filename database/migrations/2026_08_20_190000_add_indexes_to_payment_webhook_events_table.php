<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_webhook_events é a fonte de reconciliação quando o estado interno
 * divergir do Asaas - e até aqui só era consultável pelo id do evento, que é
 * exatamente o dado que ninguém tem na hora de investigar (N8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->index('criado_em');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropIndex(['criado_em']);
            $table->dropIndex(['event_type']);
        });
    }
};
