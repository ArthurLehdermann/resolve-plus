<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfis_profissionais', function (Blueprint $table): void {
            $table->json('categorias_atendidas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('perfis_profissionais', function (Blueprint $table): void {
            $table->dropColumn('categorias_atendidas');
        });
    }
};
