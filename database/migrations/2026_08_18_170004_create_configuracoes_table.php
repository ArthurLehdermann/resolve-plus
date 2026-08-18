<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracoes')) {
            Schema::create('configuracoes', function (Blueprint $table): void {
                $table->string('chave', 80)->primary();
                $table->string('valor', 255);
                $table->timestamps();
            });
        }

        $now = now();

        DB::table('configuracoes')->updateOrInsert(
            ['chave' => 'AUTO_APPROVAL_HOURS'],
            [
                'valor' => '72',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
