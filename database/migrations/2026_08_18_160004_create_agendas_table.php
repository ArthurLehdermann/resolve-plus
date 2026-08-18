<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('servico_id')->unique()->constrained('servicos')->restrictOnDelete();
            $table->date('data');
            $table->time('hora');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['data', 'hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendas');
    }
};
