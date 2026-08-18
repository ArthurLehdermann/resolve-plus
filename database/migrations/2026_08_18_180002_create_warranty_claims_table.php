<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('garantia_id')->constrained('garantias')->restrictOnDelete();
            $table->text('descricao');
            $table->json('photos');
            $table->timestamp('criado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');
    }
};
