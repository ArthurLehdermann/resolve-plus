<?php

namespace App\Services\Http\Resources;

use App\Payments\PaymentAuthorization;
use App\Proposals\Http\Resources\ProposalResource;
use App\Proposals\Proposta;
use App\Requests\Http\Resources\SolicitacaoResource;
use App\Services\Servico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Servico */
class ServicoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proposta = $this->propostaVisivel();

        return [
            'id' => $this->id,
            'proposal_id' => $this->proposta_id,
            'status' => $this->status->value,
            'started_at' => $this->inicio?->toIso8601String(),
            'finished_at' => $this->fim?->toIso8601String(),
            'notes' => $this->notas,
            'photos' => $this->fotos ?? [],
            'warranty_origin_id' => $this->garantia_origem_id,
            'client_id' => $this->clienteId(),
            'professional_id' => $this->profissionalId(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Blocos opcionais: só saem quando quem chamou carregou a relação.
            // As ações (start/finish/approve/...) devolvem o serviço cru; as
            // telas de lista e detalhe (F6/F7) carregam o contexto que precisam.
            'proposal' => $this->when(
                $proposta instanceof Proposta,
                fn (): ProposalResource => new ProposalResource($proposta),
            ),
            'request' => $this->when(
                $proposta?->relationLoaded('solicitacao') === true,
                fn (): SolicitacaoResource => new SolicitacaoResource($proposta->solicitacao),
            ),
            'schedule' => $this->whenLoaded(
                'agenda',
                fn (): ?AgendaResource => $this->agenda === null ? null : new AgendaResource($this->agenda),
            ),
            'payment' => $this->whenLoaded(
                'authorizations',
                fn (): ?array => $this->paymentPayload(),
            ),
        ];
    }

    /**
     * A proposta que descreve o serviço, quando já está em memória. Revisita de
     * garantia (INV-033) não tem proposta própria: o escopo e o valor são os do
     * serviço de origem. Devolve null se ninguém carregou a relação — o resource
     * não dispara query por conta própria, quem lista faz o eager loading.
     */
    private function propostaVisivel(): ?Proposta
    {
        if (! $this->isRevisitaGarantia()) {
            return $this->relationLoaded('proposta') ? $this->proposta : null;
        }

        if (! $this->relationLoaded('garantiaOrigem') || $this->garantiaOrigem === null) {
            return null;
        }

        $origem = $this->garantiaOrigem;

        if (! $origem->relationLoaded('servico') || $origem->servico === null) {
            return null;
        }

        return $origem->servico->relationLoaded('proposta') ? $origem->servico->proposta : null;
    }

    /**
     * Autorização vigente do serviço: a mais recente, porque reautorização de
     * cartão (INV-046) cria uma nova linha e a anterior deixa de valer.
     *
     * @return array<string, mixed>|null
     */
    private function paymentPayload(): ?array
    {
        /** @var PaymentAuthorization|null $authorization */
        $authorization = $this->authorizations->sortByDesc('criado_em')->first();

        if ($authorization === null) {
            return null;
        }

        return [
            'id' => $authorization->id,
            'valor' => $authorization->valor,
            'metodo' => $authorization->metodo->value,
            'status' => $authorization->status->value,
            'expira_em' => $authorization->expira_em?->utc()->toIso8601String(),
        ];
    }
}
