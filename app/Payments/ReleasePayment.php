<?php

namespace App\Payments;

use App\Auth\Models\Usuario;
use App\Payments\Gateway\PaymentGateway;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;

class ReleasePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
    ) {}

    public function __invoke(
        PaymentAuthorization $authorization,
        Usuario $admin,
        string $justificativa,
        ?string $ip = null,
    ): PaymentAuthorization {
        $justificativa = trim($justificativa);

        if ($justificativa === '') {
            throw new PaymentDomainException('Justificativa obrigatória para liberação manual (INV-041).', 422);
        }

        return DB::transaction(function () use ($authorization, $admin, $justificativa, $ip): PaymentAuthorization {
            $locked = PaymentAuthorization::query()
                ->lockForUpdate()
                ->findOrFail($authorization->id);

            if ($locked->status !== StatusPaymentAuthorization::Capturado) {
                throw new PaymentDomainException('Só é possível repassar autorização CAPTURADO.');
            }

            if ($locked->hasEvent(TipoPaymentEvent::Repassado)) {
                throw new PaymentDomainException('Pagamento já foi repassado.');
            }

            $openDispute = PaymentDispute::query()
                ->where('servico_id', $locked->servico_id)
                ->where('status', StatusPaymentDispute::Aberta)
                ->exists();

            if ($openDispute) {
                throw new PaymentDomainException('Disputa aberta bloqueia o repasse (INV-045).');
            }

            $walletId = $locked->wallet_id;
            $split = $locked->captureEvent()?->split;

            if ($walletId !== null && $split !== null) {
                $this->gateway->transfer($walletId, $split->valor_profissional);
            }

            ($this->recordEvent)($locked, TipoPaymentEvent::Repassado, [
                'justificativa' => $justificativa,
                'responsavel_id' => $admin->id,
                'excecao_administrativa' => $locked->servico?->status !== StatusServico::Aprovado,
            ]);

            Auditoria::query()->create([
                'usuario_id' => $admin->id,
                'acao' => 'payments.release',
                'entidade' => 'PaymentAuthorization',
                'id_entidade' => $locked->id,
                'ip' => $ip,
                'justificativa' => $justificativa,
            ]);

            return $locked->refresh();
        });
    }
}
