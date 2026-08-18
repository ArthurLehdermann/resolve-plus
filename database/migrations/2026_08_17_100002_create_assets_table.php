<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('area_id')->constrained('areas')->restrictOnDelete();
            $table->string('nome');
            $table->string('tipo')->nullable();
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
