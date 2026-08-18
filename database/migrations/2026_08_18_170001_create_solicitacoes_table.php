<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitacoes')) {
            if (! Schema::hasColumn('solicitacoes', 'property_id')) {
                Schema::table('solicitacoes', function (Blueprint $table): void {
                    $table->uuid('property_id')->nullable()->index();
                });
            }

            return;
        }

        Schema::create('solicitacoes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('usuarios')->restrictOnDelete();
            $table->uuid('property_id')->index();
            $table->string('status', 30)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes');
    }
};
