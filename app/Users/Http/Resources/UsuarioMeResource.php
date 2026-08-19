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
            'categorias_atendidas' => $this->when(
                $this->tipo === TipoUsuario::Profissional,
                fn (): array => $this->resource->perfilProfissional?->categorias_atendidas ?? []
            ),
            'trust_profile' => $this->when(
                $this->tipo === TipoUsuario::Profissional,
                fn (): array => $this->trustProfile()
            ),
        ];
    }

    /**
     * Projeção de confiança do profissional (RF005 / RN007).
     *
     * @return array<string, mixed>
     */
    private function trustProfile(): array
    {
        $perfil = $this->perfilProfissional;

        if ($perfil === null || $perfil->nivel_confianca === null) {
            return [
                'nivel_confianca' => null,
                'servicos_aprovados' => 0,
                'nota_media' => null,
                'taxa_cancelamento_pct' => 0,
                'reclamacoes_12m' => 0,
            ];
        }

        return [
            'nivel_confianca' => $perfil->nivel_confianca->value,
            'servicos_aprovados' => $perfil->servicos_aprovados,
            'nota_media' => $perfil->notaMedia(),
            'taxa_cancelamento_pct' => $perfil->taxa_cancelamento_pct,
            'reclamacoes_12m' => $perfil->reclamacoes_12m,
        ];
    }
}
