<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A Política de Privacidade promete que a cópia pré-filtro do texto some
 * quando o titular exclui a conta. `mensagens.texto_original` e
 * `propostas.observacoes_original` já eram anuláveis; `contact_leak_attempts`
 * guardava a mesma cópia com NOT NULL e ficava de fora do expurgo — é
 * justamente onde está o contato pessoal que o filtro tirou.
 *
 * As colunas passam a aceitar nulo. A linha continua de pé (padrão detectado,
 * origem e data), porque é o que sustenta a contagem de reincidência em 90
 * dias; só o texto sai.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE contact_leak_attempts ALTER COLUMN texto_original DROP NOT NULL');
        DB::statement('ALTER TABLE contact_leak_attempts ALTER COLUMN texto_filtrado DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE contact_leak_attempts SET texto_original = '' WHERE texto_original IS NULL");
        DB::statement("UPDATE contact_leak_attempts SET texto_filtrado = '' WHERE texto_filtrado IS NULL");
        DB::statement('ALTER TABLE contact_leak_attempts ALTER COLUMN texto_original SET NOT NULL');
        DB::statement('ALTER TABLE contact_leak_attempts ALTER COLUMN texto_filtrado SET NOT NULL');
    }
};
