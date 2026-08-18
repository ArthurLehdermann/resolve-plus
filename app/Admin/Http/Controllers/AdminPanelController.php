<?php

namespace App\Admin\Http\Controllers;

use App\Auth\Http\Resources\UsuarioResource;
use App\Auth\Models\Usuario;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPanelController
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    private static function resolvePagination(Request $request, int $total = 0): array
    {
        $perPage = (int) ($request->query('per_page', self::DEFAULT_PER_PAGE) ?: self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $page = (int) ($request->query('page', 1) ?: 1);
        $page = max(1, $page);

        $lastPage = (int) (intdiv(max(0, $total) + $perPage - 1, $perPage) ?: 1);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ];
    }

    public function users(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', self::DEFAULT_PER_PAGE) ?: self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $page = (int) ($request->query('page', 1) ?: 1);
        $page = max(1, $page);

        $paginator = Usuario::query()
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn (Usuario $usuario): array => (new UsuarioResource($usuario))->toArray($request))
            ->values()
            ->all();

        return ApiResponse::success([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function services(Request $request): JsonResponse
    {
        // MVP atual ainda não possui persistência de "serviços"; retornar lista vazia com paginação.
        return ApiResponse::success([
            'data' => [],
            'pagination' => self::resolvePagination($request, 0),
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        // MVP atual ainda não possui persistência de "pagamentos"; retornar lista vazia com paginação.
        return ApiResponse::success([
            'data' => [],
            'pagination' => self::resolvePagination($request, 0),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        // Estrutura mínima de dashboard; métricas específicas (W15) ainda não existem no código atual.
        $leakageMetrics = [
            'tentativas_pre_aceite' => 0,
            'tentativas_pos_aceite' => 0,
            'taxa_conclusao_pos_tentativa' => 0,
        ];

        return ApiResponse::success([
            'general_indicators' => [
                'total_usuarios' => Usuario::query()->count(),
            ],
            'leakage_metrics' => $leakageMetrics,
        ]);
    }
}
