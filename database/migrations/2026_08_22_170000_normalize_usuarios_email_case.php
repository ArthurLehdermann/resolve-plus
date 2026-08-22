<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O model passou a guardar e-mail sempre em minúsculas (Usuario::email()), mas
 * contas criadas antes disso podem ter maiúsculas gravadas - e essas ficariam
 * inacessíveis pelo login, que agora normaliza a entrada antes de comparar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Se duas contas colidirem ao normalizar, o UNIQUE de email aborta a
        // migration: é o comportamento certo, porque decidir qual das duas fica
        // é chamada de produto, não de migration.
        DB::statement('UPDATE usuarios SET email = lower(email) WHERE email <> lower(email)');
    }

    public function down(): void
    {
        // Não há o que reverter: a grafia original não fica guardada em lugar
        // nenhum, e voltar não teria como saber qual letra era maiúscula.
    }
};
