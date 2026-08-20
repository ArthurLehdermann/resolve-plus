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

Schedule::command('services:auto-approve')->hourly();
Schedule::job(new ReauthorizeExpiringPaymentsJob)->hourly();
Schedule::job(new ExpirePendingPixPaymentsJob)->hourly();
