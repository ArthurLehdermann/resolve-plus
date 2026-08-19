<?php

namespace App\Professionals\Enums;

enum TipoDocumentoProfissional: string
{
    case IdentidadeFiscal = 'IDENTIDADE_FISCAL';
    case ComprovanteEndereco = 'COMPROVANTE_ENDERECO';
    case SelfieIdentidade = 'SELFIE_IDENTIDADE';
    case SeguroRc = 'SEGURO_RC';
    case CertificadoNr10 = 'CERTIFICADO_NR10';

    /**
     * @return list<self>
     */
    public static function baseRequired(): array
    {
        return [
            self::IdentidadeFiscal,
            self::ComprovanteEndereco,
            self::SelfieIdentidade,
            self::SeguroRc,
        ];
    }
}
