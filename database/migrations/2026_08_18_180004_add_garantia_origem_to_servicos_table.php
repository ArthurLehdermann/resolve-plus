<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table): void {
            $table->foreignUuid('garantia_origem_id')
                ->nullable()
                ->after('proposta_id')
                ->constrained('garantias')
                ->restrictOnDelete();
        });

        Schema::table('servicos', function (Blueprint $table): void {
            $table->uuid('proposta_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('garantia_origem_id');
        });
    }
};
