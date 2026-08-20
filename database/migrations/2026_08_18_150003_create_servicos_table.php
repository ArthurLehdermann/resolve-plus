<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposta_id')->unique()->constrained('propostas')->restrictOnDelete();
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fim')->nullable();
            $table->text('notas')->nullable();
            $table->json('fotos')->nullable();
            $table->string('status', 30)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
