<?php

namespace App\PropertyHistory;

use DateTimeInterface;

/**
 * Criação administrativa do prontuário (testes e, no futuro, P7).
 *
 * Não há endpoint de escrita para o cliente: origem MANUAL/IMPORTADO
 * não é liberada na API nesta issue (INV-062 / B004).
 */
class RecordIntervention
{
    public function __invoke(
        string $propertyId,
        OrigemIntervention $origem,
        string $categoria,
        string $resumo,
        DateTimeInterface $data,
        ?string $servicoId = null,
        ?string $areaNome = null,
        ?string $assetNome = null,
        ?string $assetTipo = null,
    ): Intervention {
        $area = Area::query()->firstOrCreate(
            [
                'property_id' => $propertyId,
                'nome' => $this->fallback($areaNome, Area::FALLBACK_NAME),
            ],
        );

        $asset = Asset::query()->firstOrCreate(
            [
                'area_id' => $area->id,
                'nome' => $this->fallback($assetNome, Asset::FALLBACK_NAME),
            ],
            [
                'tipo' => $assetTipo,
            ],
        );

        return Intervention::query()->create([
            'asset_id' => $asset->id,
            'servico_id' => $servicoId,
            'data' => $data,
            'categoria' => $categoria,
            'resumo' => $resumo,
            'origem' => $origem,
        ]);
    }

    private function fallback(?string $nome, string $fallback): string
    {
        $trimmed = trim((string) $nome);

        return $trimmed === '' ? $fallback : $trimmed;
    }
}
