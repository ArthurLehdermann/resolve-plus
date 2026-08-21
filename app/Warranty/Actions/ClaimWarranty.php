<?php

namespace App\Warranty\Actions;

use App\Auth\Models\Usuario;
use App\Warranty\Events\GarantiaAcionada;
use App\Warranty\Exceptions\WarrantyException;
use App\Warranty\Garantia;
use App\Warranty\StatusGarantia;
use App\Warranty\WarrantyClaim;
use Illuminate\Support\Facades\DB;

class ClaimWarranty
{
    public function __construct(private readonly CreateWarrantyRevisit $createRevisit) {}

    /**
     * @param  list<string>  $photos
     */
    public function __invoke(Garantia $garantia, Usuario $cliente, string $descricao, array $photos): Garantia
    {
        $garantia = DB::transaction(function () use ($garantia, $cliente, $descricao, $photos): Garantia {
            $garantia = Garantia::query()
                ->whereKey($garantia->id)
                ->lockForUpdate()
                ->with('servico.proposta.solicitacao')
                ->firstOrFail();

            if (! $garantia->servico->isClienteDono($cliente)) {
                throw WarrantyException::forbidden('Apenas o cliente pode acionar a garantia.');
            }

            if ($garantia->status !== StatusGarantia::Ativa) {
                throw WarrantyException::conflict('Somente garantias ativas podem ser acionadas.');
            }

            WarrantyClaim::query()->create([
                'garantia_id' => $garantia->id,
                'descricao' => $descricao,
                'photos' => $photos,
            ]);

            $garantia->status = StatusGarantia::Acionada;
            $garantia->save();

            ($this->createRevisit)($garantia);

            return $garantia->refresh()->load('claims');
        });

        GarantiaAcionada::dispatch($garantia);

        return $garantia;
    }
}
