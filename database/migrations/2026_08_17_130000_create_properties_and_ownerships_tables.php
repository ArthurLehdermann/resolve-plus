<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('properties')) {
            return;
        }

        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cep', 16);
            $table->string('logradouro');
            $table->string('numero', 20);
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->string('cidade');
            $table->string('estado', 2);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('apelido')->nullable();
            $table->string('chave_endereco')->unique();
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            $table->index(['latitude', 'longitude']);
        });

        Schema::create('property_ownerships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('desde');
            $table->timestamp('ate')->nullable();
            $table->timestamp('criado_em');
            $table->timestamp('atualizado_em');

            $table->index(['cliente_id', 'ate']);
        });

        DB::statement('CREATE UNIQUE INDEX property_ownerships_one_current_owner ON property_ownerships (property_id) WHERE ate IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_ownerships');
        Schema::dropIfExists('properties');
    }
};
