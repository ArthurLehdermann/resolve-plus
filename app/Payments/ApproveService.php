<?php

namespace App\Payments;

use App\Auth\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ApproveService
{
    public function __construct(
        private readonly CapturePayment $capturePayment,
    ) {}

    public function __invoke(Servico $servico, Usuario $cliente): PaymentAuthorization
    {
        if ($servico->cliente_id !== $cliente->id) {
            throw new PaymentDomainException('Somente o cliente do serviço pode aprovar.', 403);
        }

        if ($servico->status !== StatusServico::AguardandoAprovacao) {
            throw new PaymentDomainException('Serviço não está aguardando aprovação.');
        }

        return DB::transaction(function () use ($servico): PaymentAuthorization {
            $locked = Servico::query()->lockForUpdate()->findOrFail($servico->id);

            if ($locked->status !== StatusServico::AguardandoAprovacao) {
                throw new PaymentDomainException('Serviço não está aguardando aprovação.');
            }

            $authorization = $this->authorizationForApproval($locked);

            if ($authorization->metodo === MetodoPagamento::Cartao
                && $authorization->status === StatusPaymentAuthorization::Autorizado) {
                ($this->capturePayment)($authorization, ['motivo' => 'SERVICO_APROVADO']);
            } elseif (! (
                $authorization->metodo === MetodoPagamento::Pix
                && $authorization->status === StatusPaymentAuthorization::Capturado
            )) {
                throw new PaymentDomainException('Não há autorização capturável para este serviço.');
            }

            $locked->forceFill(['status' => StatusServico::Aprovado])->save();

            return $authorization->refresh();
        });
    }

    private function authorizationForApproval(Servico $servico): PaymentAuthorization
    {
        $autorizado = $servico->authorizations()
            ->where('status', StatusPaymentAuthorization::Autorizado)
            ->first();

        if ($autorizado !== null) {
            return $autorizado;
        }

        $capturado = $servico->authorizations()
            ->where('status', StatusPaymentAuthorization::Capturado)
            ->latest('criado_em')
            ->first();

        if ($capturado !== null) {
            return $capturado;
        }

        throw new PaymentDomainException('Serviço sem autorização de pagamento.');
    }
}
