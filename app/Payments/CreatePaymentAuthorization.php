<?php

namespace App\Payments;

use App\Services\Servico;

/**
 * INV-033: revisitas de garantia não geram nova cobrança ao cliente.
 */
class CreatePaymentAuthorization
{
    public function forServico(Servico $servico, int $valor, MetodoPagamento $metodo): ?PaymentAuthorization
    {
        if ($servico->isRevisitaGarantia()) {
            return null;
        }

        return PaymentAuthorization::query()->create([
            'servico_id' => $servico->id,
            'valor' => $valor,
            'metodo' => $metodo,
            'status' => $metodo === MetodoPagamento::Pix
                ? StatusPaymentAuthorization::Capturado
                : StatusPaymentAuthorization::Autorizado,
            'expira_em' => $metodo === MetodoPagamento::Pix ? null : now()->addDays(7),
        ]);
    }
}
