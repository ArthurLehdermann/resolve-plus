<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('servicos')) {
            if (! Schema::hasColumn('servicos', 'notas')) {
                Schema::table('servicos', function (Blueprint $table): void {
                    $table->text('notas')->nullable();
                });
            }

            if (! Schema::hasColumn('servicos', 'fotos')) {
                Schema::table('servicos', function (Blueprint $table): void {
                    $table->json('fotos')->nullable();
                });
            }

            return;
        }

        Schema::create('servicos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposta_id')->nullable()->unique()->constrained('propostas')->restrictOnDelete();
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
