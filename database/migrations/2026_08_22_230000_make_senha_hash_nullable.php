<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cliente e profissional entram por código de e-mail ou Google: conta criada
 * por esses caminhos não tem senha nenhuma. A coluna continua existindo porque
 * o painel administrativo ainda autentica por senha.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE usuarios ALTER COLUMN senha_hash DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE usuarios SET senha_hash = '' WHERE senha_hash IS NULL");
        DB::statement('ALTER TABLE usuarios ALTER COLUMN senha_hash SET NOT NULL');
    }
};
