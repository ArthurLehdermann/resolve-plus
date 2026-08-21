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
 * Pix: o POST /v3/payments do Asaas com billingType PIX cria uma cobrança
 * PENDENTE - não confirma na hora. A autorização nasce com o status que o
 * gateway efetivamente devolveu ($charge->status), não uma constante
 * escrita à mão; PENDENTE só vira CAPTURADO quando o webhook do Asaas
 * confirma o pagamento (App\Payments\Webhooks\HandleAsaasWebhook). Gravar
 * CAPTURADO direto na criação fabrica um pagamento que pode nunca ter
 * acontecido - a plataforma repassaria dinheiro próprio ao profissional.
 *
 * `customerId` aqui é o id do Usuario (placeholder): este repositório
 * ainda não tem um fluxo de provisionamento de customer no Asaas. Serve
 * para o gateway fake; antes de ligar PAYMENT_GATEWAY=asaas em produção,
 * precisa existir um customer real por usuário.
 */
class CreatePaymentAuthorization
{
    private const PIX_STATUS_JA_CONFIRMADO = ['CONFIRMED', 'RECEIVED'];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
        private readonly CreatePixSplit $createSplit,
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
        $valor = $this->valorProposta($servico);
        $charge = $this->gateway->chargePix($customerId, $valor);

        $confirmado = in_array($charge->status, self::PIX_STATUS_JA_CONFIRMADO, true);

        $authorization = $this->persist(
            fn () => $this->createAndRecord($servico, $valor, MetodoPagamento::Pix, $customerId, [
                'status' => $confirmado ? StatusPaymentAuthorization::Capturado : StatusPaymentAuthorization::Pendente,
                'gateway_payment_id' => $charge->id,
                'credit_card_token' => null,
                'expira_em' => null,
            ], $confirmado ? TipoPaymentEvent::Capturado : TipoPaymentEvent::Criado),
            $charge->id,
        );

        if ($confirmado) {
            $event = $authorization->captureEvent();

            if ($event !== null) {
                ($this->createSplit)($event, $valor);
            }
        }

        return $authorization;
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
