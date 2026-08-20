<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Payments\PaymentAuthorization;
use App\Payments\StatusPaymentAuthorization;
use App\Services\Events\ServiceStarted;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StartService
{
    public function __invoke(Servico $servico, Usuario $usuario): Servico
    {
        return DB::transaction(function () use ($servico, $usuario): Servico {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $servico->isProfissionalResponsavel($usuario)) {
                throw ServiceException::forbidden(
                    'Apenas o profissional da proposta aceita pode iniciar este serviço.',
                );
            }

            if ($servico->status !== StatusServico::Agendado) {
                throw ServiceException::conflict(
                    'Somente serviços agendados podem ser iniciados.',
                );
            }

            $this->ensurePaymentConfirmed($servico);

            $servico->status = StatusServico::EmAndamento;
            $servico->inicio = now();
            $servico->save();

            ServiceStarted::dispatch($servico);

            return $servico->refresh();
        });
    }

    /**
     * Pix nasce PENDENTE (INV-C1/CreatePaymentAuthorization) e só vira
     * CAPTURADO quando o webhook do Asaas confirma. Sem esse gate o
     * profissional inicia e conclui o serviço, garantia é emitida e o
     * prontuário é gravado com o pagamento nunca confirmado - só descobre
     * no repasse, quando ReleasePayment já recusa por falta de CAPTURADO.
     */
    private function ensurePaymentConfirmed(Servico $servico): void
    {
        $authorization = PaymentAuthorization::query()
            ->where('servico_id', $servico->id)
            ->latest('criado_em')
            ->first();

        if ($authorization === null) {
            Log::error('INCIDENTE: início de serviço sem nenhuma autorização de pagamento associada (INV-C1).', [
                'servico_id' => $servico->id,
            ]);

            throw ServiceException::conflict(
                'Serviço sem autorização de pagamento associada; não é possível iniciar.',
            );
        }

        if ($authorization->status === StatusPaymentAuthorization::Pendente) {
            throw ServiceException::conflict(
                'Pagamento Pix ainda não foi confirmado; o serviço não pode ser iniciado.',
            );
        }

        if (! in_array($authorization->status, [StatusPaymentAuthorization::Autorizado, StatusPaymentAuthorization::Capturado], true)) {
            throw ServiceException::conflict(
                'Pagamento não está em estado válido para iniciar o serviço.',
            );
        }
    }
}
