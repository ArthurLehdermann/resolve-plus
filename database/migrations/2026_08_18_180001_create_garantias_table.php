<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garantias', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('servico_id')->unique()->constrained('servicos')->restrictOnDelete();
            $table->timestamp('inicio');
            $table->timestamp('fim');
            $table->string('status', 20)->index();
            $table->string('responsavel_financeiro', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garantias');
    }
};
