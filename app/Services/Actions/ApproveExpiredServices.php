<?php

namespace App\Services\Actions;

use App\Admin\Configuracao;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Database\Eloquent\Builder;

class ApproveExpiredServices
{
    public function __construct(private readonly ApproveService $approveService) {}

    public function __invoke(): int
    {
        $hours = Configuracao::inteiro('AUTO_APPROVAL_HOURS');
        $limite = now()->subHours($hours);
        $aprovados = 0;

        Servico::query()
            ->where('status', StatusServico::AguardandoAprovacao)
            ->whereNotNull('fim')
            ->where('fim', '<=', $limite)
            ->whereDoesntHave('disputes', function (Builder $query): void {
                $query->where('tipo', TipoPaymentDispute::ContestacaoConclusao)
                    ->where('status', StatusPaymentDispute::Aberta);
            })
            ->orderBy('fim')
            ->each(function (Servico $servico) use (&$aprovados): void {
                $antes = $servico->status;

                try {
                    $atualizado = $this->approveService->bySystem($servico);
                } catch (ServiceException) {
                    return;
                }

                if ($antes === StatusServico::AguardandoAprovacao
                    && $atualizado->status === StatusServico::Aprovado) {
                    $aprovados++;
                }
            });

        return $aprovados;
    }
}
