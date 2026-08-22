<?php

namespace App\Proposals\Http\Resources;

use App\Proposals\Proposta;
use App\Services\Servico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proposta */
class ProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->solicitacao_id,
            'price' => $this->valor,
            'deadline_days' => $this->prazo_dias,
            'warranty_days' => $this->garantia_dias,
            'notes' => $this->observacoes,
            'warning' => $this->when(
                $this->contactLeakWarning !== null,
                $this->contactLeakWarning,
            ),
            'status' => $this->status->value,
            'professional' => [
                'id' => $this->profissional?->id,
                'nome' => $this->profissional?->nome,
                // RN026: o cliente compara propostas por reputação, não só por
                // preço. Sai null enquanto o profissional não tem perfil de
                // confiança calculado (conta nova), e a tela trata isso.
                'trust_level' => $this->profissional?->perfilProfissional?->nivel_confianca?->value,
                'average_rating' => $this->profissional?->perfilProfissional?->notaMedia(),
            ],
            'service' => $this->when(
                $this->relationLoaded('servico') && $this->servico instanceof Servico,
                fn (): array => [
                    'id' => $this->servico->id,
                    'status' => $this->servico->status->value,
                    'proposal_id' => $this->servico->proposta_id,
                ],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
