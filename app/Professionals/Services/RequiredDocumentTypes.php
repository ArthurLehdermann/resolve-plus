<?php

namespace App\Professionals\Services;

use App\Professionals\Enums\TipoDocumentoProfissional;

final class RequiredDocumentTypes
{
    /**
     * @param  list<string>  $categoriasCodigos
     * @return list<TipoDocumentoProfissional>
     */
    public static function forCategorias(array $categoriasCodigos): array
    {
        $required = TipoDocumentoProfissional::baseRequired();

        if (in_array('eletrica', $categoriasCodigos, true)) {
            $required[] = TipoDocumentoProfissional::CertificadoNr10;
        }

        return $required;
    }
}
