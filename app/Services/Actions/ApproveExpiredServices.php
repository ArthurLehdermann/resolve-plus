<?php

namespace App\Services\Actions;

use App\Admin\Configuracao;
use App\Services\Servico;
use App\Services\StatusServico;

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
            ->orderBy('fim')
            ->each(function (Servico $servico) use (&$aprovados): void {
                $this->approveService->bySystem($servico);
                $aprovados++;
            });

        return $aprovados;
    }
}
