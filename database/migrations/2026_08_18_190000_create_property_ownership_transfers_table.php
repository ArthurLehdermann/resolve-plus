<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_ownership_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignUuid('de_cliente_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('para_cliente_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('para_email', 150);
            $table->string('status', 20);
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');
            $table->timestamp('expira_em');

            $table->index('property_id');
            $table->index('para_cliente_id');
            $table->index('para_email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_ownership_transfers');
    }
};
