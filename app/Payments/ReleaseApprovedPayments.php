<?php

namespace App\Payments;

use App\Payments\Gateway\PaymentGateway;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repasse automático (`adr/ADR-004-prazo-aceite-automatico.md`): "Sem
 * manifestação -> Serviço aprovado automaticamente -> captura + repasse
 * (INV-041)". A mesma janela de 72h que aprova o serviço automaticamente
 * é o gatilho documentado do evento REPASSADO (`02-state-machine.md`
 * §4b), não existe uma segunda espera depois de Aprovado. Antes deste
 * job, o único caminho para REPASSADO era `POST /payments/{id}/release`
 * (ReleasePayment), manual, Admin - nenhum profissional recebia pagamento
 * sem um clique humano, mesmo dentro da janela que a documentação sempre
 * descreveu como automática (achado de auditoria, 2026-08-20).
 *
 * Roda por cima de `Servico.status = APROVADO` (não por tempo desde a
 * aprovação): a captura de cartão é assíncrona (CapturePaymentJob, via
 * fila) e a de Pix já aconteceu antes da aprovação (webhook). Este job só
 * age quando a autorização já está CAPTURADO de fato, então não precisa
 * coordenar com esses outros jobs - só espera a próxima execução horária.
 *
 * Escopo deliberadamente restrito a `Servico.status = APROVADO`: não
 * cobre o repasse da multa do Cenário B (`foundation/03-cancellation-rules.md`,
 * autorização CAPTURADO com `Servico.status = CANCELADO`). Nesse caminho
 * o `PaymentSplit` já existente na autorização foi calculado sobre o
 * valor cheio da proposta (INV-044, imutável), não sobre a multa retida -
 * repassar `split->valor_profissional` automaticamente ali pagaria o
 * profissional pelo serviço inteiro quando só a multa deveria ficar
 * retida. Esse caminho continua exigindo `POST /payments/{id}/release`
 * manual até existir um split proporcional à multa.
 */
class ReleaseApprovedPayments
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
    ) {}

    public function __invoke(): int
    {
        $ids = PaymentAuthorization::query()
            ->where('status', StatusPaymentAuthorization::Capturado)
            ->whereHas('servico', function ($query): void {
                $query->where('status', StatusServico::Aprovado);
            })
            ->whereDoesntHave('events', function ($query): void {
                $query->where('tipo', TipoPaymentEvent::Repassado);
            })
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            if ($this->process((string) $id)) {
                $processed++;
            }
        }

        return $processed;
    }

    private function process(string $authorizationId): bool
    {
        return DB::transaction(function () use ($authorizationId): bool {
            $authorization = PaymentAuthorization::query()
                ->with('servico')
                ->lockForUpdate()
                ->find($authorizationId);

            if ($authorization === null
                || $authorization->status !== StatusPaymentAuthorization::Capturado
                || $authorization->hasRepasse()) {
                return false;
            }

            $servico = $authorization->servico;

            if ($servico === null || $servico->status !== StatusServico::Aprovado) {
                return false;
            }

            $openDispute = PaymentDispute::query()
                ->where('servico_id', $authorization->servico_id)
                ->where('status', StatusPaymentDispute::Aberta)
                ->exists();

            if ($openDispute) {
                return false;
            }

            $walletId = $authorization->wallet_id;
            $split = $authorization->captureEvent()?->split;

            if ($walletId !== null && $split !== null) {
                $this->gateway->transfer($walletId, $split->valor_profissional);
            } elseif ($walletId !== null) {
                // Mesma anomalia de ReleasePayment: captura sem split não
                // deveria existir mais (CreatePixSplit/CapturePayment
                // cobrem todo caminho legítimo de captura sob Aprovado),
                // mas nunca grava REPASSADO em silêncio se acontecer.
                Log::error('INCIDENTE: repasse automático sem PaymentSplit - nenhuma transferência real foi feita ao profissional.', [
                    'authorization_id' => $authorization->id,
                    'servico_id' => $authorization->servico_id,
                    'wallet_id' => $walletId,
                ]);
            }

            ($this->recordEvent)($authorization, TipoPaymentEvent::Repassado, [
                'motivo' => 'REPASSE_AUTOMATICO_SERVICO_APROVADO',
            ]);

            return true;
        });
    }
}
