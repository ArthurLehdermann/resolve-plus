<?php

namespace App\Privacy;

use RuntimeException;

/**
 * Exclusão pedida enquanto ainda existe combinado aberto com a outra parte.
 *
 * Exceção própria, e não RuntimeException genérica: o controller devolve 409
 * só para este caso: falha de storage ou de banco durante a exclusão precisa
 * estourar como erro, não virar "conclua seu serviço primeiro".
 */
final class ContaComServicoEmAndamento extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Existe serviço em andamento nesta conta. Conclua ou cancele antes de excluir.');
    }
}
