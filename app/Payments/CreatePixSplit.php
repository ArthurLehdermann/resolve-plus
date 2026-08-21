<?php

namespace App\Payments;

/**
 * Comissão da plataforma sobre um Pix confirmado (INV-044). Cartão calcula
 * o PaymentSplit dentro de CapturePayment, no mesmo lugar que faz a
 * captura. Pix confirma em três lugares diferentes (HandleAsaasWebhook,
 * ExpirePendingPixPayments::confirmIfStillPending, CreatePaymentAuthorization
 * no raro caso de confirmação imediata) e nenhum deles calculava o split
 * (achado de auditoria, 2026-08-20): sem PaymentSplit, ReleasePayment lia
 * `captureEvent()?->split` como null, o `if` de transferência falhava
 * silenciosamente, e o evento REPASSADO era gravado como se o dinheiro
 * tivesse saído para o profissional - sem transferência real, sem log,
 * sem exceção.
 *
 * Não é usado por HandleAsaasWebhook::registrarConfirmacaoTardia de
 * propósito: aquele caminho confirma um Pix que já foi EXPIRADO/CANCELADO
 * e imediatamente gera PaymentRefund integral, então não sobra nada para
 * repassar ao profissional. Calcular split ali só criaria um registro que
 * poderia, por engano, autorizar um repasse indevido a partir de dinheiro
 * já devolvido ao cliente.
 */
class CreatePixSplit
{
    public function __construct(
        private readonly CommissionRate $commissionRate,
        private readonly SplitCalculator $splitCalculator,
    ) {}

    public function __invoke(PaymentEvent $captureEvent, int $valor): PaymentSplit
    {
        $aliquota = $this->commissionRate->current();
        $split = $this->splitCalculator->calculate($valor, $aliquota);

        return PaymentSplit::query()->create([
            'payment_event_id' => $captureEvent->id,
            ...$split,
        ]);
    }
}
