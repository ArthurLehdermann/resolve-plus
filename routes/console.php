<?php

use App\Payments\Jobs\ExpirePendingPixPaymentsJob;
use App\Payments\Jobs\ReauthorizeExpiringPaymentsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:reauthorize', function () {
    $this->comment('Reautorizando cobranças de cartão próximas da expiração (INV-046)...');
    dispatch_sync(new ReauthorizeExpiringPaymentsJob);
    $this->info('Concluído.');
})->purpose('Reautoriza cobranças de cartão próximas da expiração (INV-046)');

// withoutOverlapping: se uma corrida atrasar (gateway lento, muitos Pix
// vencendo), o próximo disparo horário não pode empilhar por cima -
// duas instâncias do mesmo job mexendo nas mesmas autorizações é
// exatamente o tipo de corrida que gerou N9.
Schedule::command('services:auto-approve')->hourly()->withoutOverlapping();
Schedule::job(new ReauthorizeExpiringPaymentsJob)->hourly()->withoutOverlapping();
Schedule::job(new ExpirePendingPixPaymentsJob)->hourly()->withoutOverlapping();
