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
    private const STATUS_CONFIRMADO = ['CONFIRMED', 'RECEIVED'];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
        private readonly CreatePixSplit $createSplit,
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
     * Chamadas HTTP (find/cancel, até 15s cada) rodam fora de qualquer
     * transação/lock de propósito (N10): cinquenta Pix expirando com o
     * Asaas lento não podem segurar uma transação com lockForUpdate por vez
     * dentro do laço de __invoke.
     *
     * Antes de decidir qualquer coisa, consulta o status real no gateway
     * (N9): status local pode estar desatualizado se o webhook ainda não
     * chegou. Só cancela o que o Asaas ainda diz PENDING; o que já foi
     * confirmado lá fora é capturado em vez de expirado, sem depender do
     * reembolso incidental do webhook (HandleAsaasWebhook::registrarConfirmacaoTardia)
     * como única rede de segurança. Falha na consulta ou no cancel aborta
     * esta autorização - nunca warning-e-segue, porque marcar EXPIRADO por
     * cima de um pagamento que aconteceu é dinheiro do cliente sumindo sem
     * serviço e sem reembolso. A tentativa seguinte do job horário cobre
     * falha transitória de rede.
     */
    private function process(string $authorizationId): void
    {
        $authorization = PaymentAuthorization::query()->find($authorizationId);

        if ($authorization === null || $authorization->status !== StatusPaymentAuthorization::Pendente) {
            return;
        }

        if ($authorization->gateway_payment_id === null) {
            $this->expireIfStillPending($authorizationId);

            return;
        }

        try {
            $status = $this->gateway->find($authorization->gateway_payment_id)->status;
        } catch (GatewayException $exception) {
            Log::error('INCIDENTE: falha ao consultar status do Pix pendente expirado no gateway - autorização mantida PENDENTE.', [
                'authorization_id' => $authorization->id,
                'servico_id' => $authorization->servico_id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (in_array($status, self::STATUS_CONFIRMADO, true)) {
            $this->confirmIfStillPending($authorizationId);

            return;
        }

        if ($status === 'PENDING') {
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

        $this->expireIfStillPending($authorizationId);
    }

    /**
     * O gateway já diz confirmado, mas o webhook ainda não processou -
     * confirma direto em vez de esperar. Relock e reconfirma PENDENTE:
     * entre a consulta ao gateway (sem lock) e aqui, o webhook pode ter
     * chegado primeiro.
     */
    private function confirmIfStillPending(string $authorizationId): void
    {
        DB::transaction(function () use ($authorizationId): void {
            $authorization = PaymentAuthorization::query()
                ->lockForUpdate()
                ->find($authorizationId);

            if ($authorization === null || $authorization->status !== StatusPaymentAuthorization::Pendente) {
                return;
            }

            $event = ($this->recordEvent)($authorization, TipoPaymentEvent::Capturado, [
                'motivo' => 'RECONCILIACAO_GATEWAY_ANTES_DE_EXPIRAR',
            ]);

            ($this->createSplit)($event, $authorization->valor);
        });
    }

    /**
     * Relock e reconfirma PENDENTE: entre o cancel (sem lock) e aqui, o
     * webhook pode ter confirmado o pagamento primeiro.
     */
    private function expireIfStillPending(string $authorizationId): void
    {
        DB::transaction(function () use ($authorizationId): void {
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
