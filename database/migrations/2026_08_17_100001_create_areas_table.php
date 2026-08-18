<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id')->index();
            $table->string('nome');
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
