<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 50)->unique();
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->jsonb('template_escopo');
            $table->timestamps();

            $table->index('ativo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
