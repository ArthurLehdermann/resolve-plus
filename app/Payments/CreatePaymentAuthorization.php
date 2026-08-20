<?php

namespace App\Payments;

use App\Payments\Gateway\GatewayException;
use App\Payments\Gateway\PaymentGateway;
use App\Services\Servico;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cria a autorização de pagamento no aceite da proposta (INV-C1).
 *
 * Cartão: autoriza agora, captura só quando o serviço é aprovado
 * (CapturePaymentOnApproval / CapturePaymentJob).
 *
 * Pix está desabilitado (ver chargePix): o POST /v3/payments do Asaas com
 * billingType PIX cria uma cobrança PENDENTE, não confirma na hora - a
 * confirmação chega depois por webhook, que este repositório não
 * implementa. Sem isso, gravar a autorização como CAPTURADO na criação é
 * fabricar um pagamento que pode nunca ter acontecido (a plataforma
 * repassaria dinheiro próprio ao profissional). Reativar Pix exige status
 * PENDENTE em StatusPaymentAuthorization + endpoint de webhook assinado e
 * idempotente.
 *
 * `customerId` aqui é o id do Usuario (placeholder): este repositório
 * ainda não tem um fluxo de provisionamento de customer no Asaas. Serve
 * para o gateway fake; antes de ligar PAYMENT_GATEWAY=asaas em produção,
 * precisa existir um customer real por usuário.
 */
class CreatePaymentAuthorization
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
    ) {}

    public function __invoke(
        Servico $servico,
        MetodoPagamento $metodo,
        string $customerId,
        ?string $creditCardToken = null,
    ): PaymentAuthorization {
        return match ($metodo) {
            MetodoPagamento::Cartao => $this->authorizeCard($servico, $customerId, $creditCardToken),
            MetodoPagamento::Pix => $this->chargePix($servico, $customerId),
        };
    }

    private function authorizeCard(Servico $servico, string $customerId, ?string $creditCardToken): PaymentAuthorization
    {
        if ($creditCardToken === null || $creditCardToken === '') {
            throw new PaymentDomainException('Token do cartão é obrigatório para pagamento com cartão.', 422);
        }

        $valor = $this->valorProposta($servico);
        $charge = $this->gateway->authorizeCard($customerId, $valor, $creditCardToken);

        return $this->persist(
            fn () => $this->createAndRecord($servico, $valor, MetodoPagamento::Cartao, $customerId, [
                'status' => StatusPaymentAuthorization::Autorizado,
                'gateway_payment_id' => $charge->id,
                'credit_card_token' => $charge->creditCardToken ?? $creditCardToken,
                'expira_em' => $charge->expiresAt ?? now()->addDays((int) config('payments.authorization_days')),
            ], TipoPaymentEvent::Autorizado),
            $charge->id,
        );
    }

    private function chargePix(Servico $servico, string $customerId): PaymentAuthorization
    {
        throw new PaymentDomainException(
            'Pagamento via Pix está temporariamente indisponível: a confirmação depende de '
            .'webhook do Asaas, que ainda não existe neste ambiente. Use cartão.',
            422,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createAndRecord(
        Servico $servico,
        int $valor,
        MetodoPagamento $metodo,
        string $customerId,
        array $extra,
        TipoPaymentEvent $evento,
    ): PaymentAuthorization {
        $authorization = PaymentAuthorization::query()->create([
            'servico_id' => $servico->id,
            'valor' => $valor,
            'metodo' => $metodo,
            'gateway_customer_id' => $customerId,
            ...$extra,
        ]);

        ($this->recordEvent)($authorization, $evento, [
            'gateway_payment_id' => $extra['gateway_payment_id'],
        ]);

        return $authorization->refresh();
    }

    /**
     * Persiste após o gateway já ter confirmado. Se a persistência falhar,
     * desfaz a cobrança/autorização no gateway em vez de deixar dinheiro
     * preso sem registro local (mesmo padrão de ReauthorizeExpiringPayments::rotate).
     */
    private function persist(callable $write, string $gatewayChargeId): PaymentAuthorization
    {
        try {
            return DB::transaction($write);
        } catch (Throwable $exception) {
            try {
                $this->gateway->cancel($gatewayChargeId);
            } catch (GatewayException) {
                //
            }

            throw $exception;
        }
    }

    private function valorProposta(Servico $servico): int
    {
        $valor = $servico->proposta?->valor;

        if ($valor === null) {
            throw new PaymentDomainException('Serviço sem proposta associada; não é possível autorizar pagamento.', 409);
        }

        return $valor;
    }
}
