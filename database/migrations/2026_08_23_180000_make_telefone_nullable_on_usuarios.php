<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Exclusão de conta a pedido do titular apaga o telefone (LGPD, art. 18, VI).
 * Com NOT NULL, o único jeito de "apagar" seria gravar string vazia — dado
 * falso no lugar de ausência de dado. A coluna passa a aceitar nulo; o
 * cadastro continua exigindo telefone pela validação da API.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE usuarios ALTER COLUMN telefone DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE usuarios SET telefone = '' WHERE telefone IS NULL");
        DB::statement('ALTER TABLE usuarios ALTER COLUMN telefone SET NOT NULL');
    }
};
