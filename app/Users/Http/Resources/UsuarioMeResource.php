<?php

namespace App\Users\Http\Resources;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Http\Resources\UsuarioResource;
use Illuminate\Http\Request;

class UsuarioMeResource extends UsuarioResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'trust_profile' => $this->when(
                $this->tipo === TipoUsuario::Profissional,
                fn (): array => $this->trustProfile()
            ),
        ];
    }

    /**
     * Projeção de confiança do profissional (RF005 / RN007).
     * PerfilProfissional ainda não está persistido neste recorte — devolve
     * zeros/nulos até o domínio de reputação materializar o registro.
     *
     * @return array<string, mixed>
     */
    private function trustProfile(): array
    {
        return [
            'nivel_confianca' => null,
            'servicos_aprovados' => 0,
            'nota_media' => null,
            'taxa_cancelamento_pct' => 0,
            'reclamacoes_12m' => 0,
        ];
    }
}
