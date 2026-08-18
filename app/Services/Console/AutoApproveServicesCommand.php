<?php

namespace App\Services\Console;

use App\Services\Actions\ApproveExpiredServices;
use Illuminate\Console\Command;

class AutoApproveServicesCommand extends Command
{
    protected $signature = 'services:auto-approve';

    protected $description = 'Aprova automaticamente serviços cuja janela AUTO_APPROVAL_HOURS expirou sem contestação (INV-031).';

    public function handle(ApproveExpiredServices $approveExpired): int
    {
        $aprovados = $approveExpired();

        $this->info("Serviços aprovados automaticamente: {$aprovados}");

        return self::SUCCESS;
    }
}
