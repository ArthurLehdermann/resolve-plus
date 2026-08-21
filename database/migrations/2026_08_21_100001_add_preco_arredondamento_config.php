<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuracoes')->insert([
            'chave' => 'PRECO_ARREDONDAMENTO_CENTAVOS',
            'valor' => '1000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('configuracoes')->where('chave', 'PRECO_ARREDONDAMENTO_CENTAVOS')->delete();
    }
};
