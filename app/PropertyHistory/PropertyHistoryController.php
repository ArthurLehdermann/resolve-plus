<?php

namespace App\PropertyHistory;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyHistoryController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $property = Property::query()->with('currentOwnership')->find($id);

        if ($property === null) {
            return ApiResponse::error('Imóvel não encontrado.', 404);
        }

        if (! $property->isCurrentOwner($usuario)) {
            return ApiResponse::error('Somente o dono vigente pode ver o histórico do imóvel.', 403);
        }

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
