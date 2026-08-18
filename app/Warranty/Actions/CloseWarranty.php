<?php

namespace App\Warranty\Actions;

use App\Warranty\Exceptions\WarrantyException;
use App\Warranty\Garantia;
use App\Warranty\StatusGarantia;
use Illuminate\Support\Facades\DB;

class CloseWarranty
{
    public function __invoke(Garantia $garantia): Garantia
    {
        return DB::transaction(function () use ($garantia): Garantia {
            $garantia = Garantia::query()
                ->whereKey($garantia->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($garantia->status === StatusGarantia::Encerrada) {
                return $garantia;
            }

            if ($garantia->status === StatusGarantia::Acionada) {
                $garantia->status = StatusGarantia::Encerrada;
                $garantia->save();

                return $garantia->refresh();
            }

            if ($garantia->status === StatusGarantia::Ativa) {
                $garantia->status = StatusGarantia::Expirada;
                $garantia->save();

                return $garantia->refresh();
            }

            throw WarrantyException::conflict('Garantia não pode ser encerrada neste estado.');
        });
    }
}
