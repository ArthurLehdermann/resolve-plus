<?php

namespace App\Payments;

use App\Payments\Gateway\GatewayException;
use App\Payments\Gateway\PaymentGateway;
use App\Proposals\StatusProposta;
use App\Requests\StatusSolicitacao;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sem isso, um Pix nunca pago (cliente aceitou e fechou o app) deixa a
 * autorização PENDENTE, o serviço AGENDADO e a solicitação CONTRATADA para
 * sempre - com todas as outras propostas já recusadas por INV-011, o
 * cliente fica sem meio de recontratar sem abrir uma solicitação nova (N5).
 */
class ExpirePendingPixPayments
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
    ) {}

    public function __invoke(): int
    {
        $cutoff = now()->subHours((int) config('payments.pix_expiration_hours'));

        $ids = PaymentAuthorization::query()
            ->where('metodo', MetodoPagamento::Pix)
            ->where('status', StatusPaymentAuthorization::Pendente)
            ->where('criado_em', '<=', $cutoff)
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            $this->process((string) $id);
            $processed++;
        }

        return $processed;
    }

    /**
     * O cancel é HTTP (até 15s) e roda fora de qualquer transação/lock de
     * propósito (N10): cinquenta Pix expirando com o Asaas lento não podem
     * segurar uma transação com lockForUpdate por vez dentro do laço de
     * __invoke. O motivo mais provável do cancel falhar é o Pix já ter sido
     * pago - Asaas não remove cobrança recebida - então falha aqui aborta
     * esta autorização (N9): não é warning-e-segue, porque marcar EXPIRADO
     * por cima de um pagamento que na verdade aconteceu é dinheiro do
     * cliente sumindo sem serviço e sem reembolso. A tentativa seguinte do
     * job horário cobre o caso de falha transitória de rede.
     */
    private function process(string $authorizationId): void
    {
        $authorization = PaymentAuthorization::query()->find($authorizationId);

        if ($authorization === null || $authorization->status !== StatusPaymentAuthorization::Pendente) {
            return;
        }

        if ($authorization->gateway_payment_id !== null) {
            try {
                $this->gateway->cancel($authorization->gateway_payment_id);
            } catch (GatewayException $exception) {
                Log::error('INCIDENTE: falha ao cancelar Pix pendente expirado no gateway - provável pagamento já recebido, autorização mantida PENDENTE.', [
                    'authorization_id' => $authorization->id,
                    'servico_id' => $authorization->servico_id,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }
        }

        DB::transaction(function () use ($authorizationId): void {
            // Relock e reconfirma PENDENTE: entre o cancel acima (sem lock)
            // e aqui, o webhook pode ter confirmado o pagamento primeiro.
            $authorization = PaymentAuthorization::query()
                ->lockForUpdate()
                ->with('servico.proposta.solicitacao')
                ->find($authorizationId);

            if ($authorization === null || $authorization->status !== StatusPaymentAuthorization::Pendente) {
                return;
            }

            ($this->recordEvent)($authorization, TipoPaymentEvent::Expirado, [
                'motivo' => 'PIX_PENDENTE_EXPIRADO',
            ]);

            $this->liberarSolicitacao($authorization);
        });
    }

    private function liberarSolicitacao(PaymentAuthorization $authorization): void
    {
        $servico = $authorization->servico;

        if ($servico === null || $servico->status !== StatusServico::Agendado) {
            return;
        }

        $servico->status = StatusServico::Cancelado;
        $servico->save();

        $proposta = $servico->proposta;

        if ($proposta === null) {
            return;
        }

        if ($proposta->status === StatusProposta::Aceita) {
            $proposta->status = StatusProposta::Recusada;
            $proposta->save();
        }

        $solicitacao = $proposta->solicitacao;

        if ($solicitacao !== null && $solicitacao->status === StatusSolicitacao::Contratada) {
            $solicitacao->status = StatusSolicitacao::RecebendoPropostas;
            $solicitacao->save();
        }
    }
}
