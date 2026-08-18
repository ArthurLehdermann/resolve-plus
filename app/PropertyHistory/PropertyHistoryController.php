<?php

namespace App\PropertyHistory;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PropertyHistoryController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $areas = Area::query()
            ->where('property_id', $id)
            ->with([
                'assets' => fn ($query) => $query->orderBy('nome'),
                'assets.interventions' => fn ($query) => $query->orderBy('data'),
            ])
            ->orderBy('nome')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'property_id' => $id,
                'areas' => $areas->map(fn (Area $area): array => [
                    'id' => $area->id,
                    'nome' => $area->nome,
                    'assets' => $area->assets->map(fn (Asset $asset): array => [
                        'id' => $asset->id,
                        'nome' => $asset->nome,
                        'tipo' => $asset->tipo,
                        'interventions' => $asset->interventions->map(fn (Intervention $intervention): array => [
                            'id' => $intervention->id,
                            'asset_id' => $intervention->asset_id,
                            'servico_id' => $intervention->servico_id,
                            'data' => $intervention->data?->utc()->toIso8601String(),
                            'categoria' => $intervention->categoria,
                            'resumo' => $intervention->resumo,
                            'origem' => $intervention->origem->value,
                            'confiabilidade' => $intervention->confiabilidade->value,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ],
        ]);
    }
}
