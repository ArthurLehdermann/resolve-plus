<?php

namespace App\Admin\Http\Controllers;

use App\Auth\Http\Resources\UsuarioResource;
use App\Auth\Models\Usuario;
use App\Payments\PaymentAuthorization;
use App\Services\Http\Resources\ServicoResource;
use App\Services\Servico;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPanelController
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function users(Request $request): JsonResponse
    {
        [$perPage, $page] = $this->paginationParams($request);

        $paginator = Usuario::query()
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn (Usuario $usuario): array => (new UsuarioResource($usuario))->toArray($request))
            ->values()
            ->all();

        return $this->paginated($paginator, $items);
    }

    public function services(Request $request): JsonResponse
    {
        [$perPage, $page] = $this->paginationParams($request);

        $paginator = Servico::query()
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn (Servico $servico): array => (new ServicoResource($servico))->toArray($request))
            ->values()
            ->all();

        return $this->paginated($paginator, $items);
    }

    public function payments(Request $request): JsonResponse
    {
        [$perPage, $page] = $this->paginationParams($request);

        $paginator = PaymentAuthorization::query()
            ->orderByDesc('criado_em')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn (PaymentAuthorization $authorization): array => [
                'id' => $authorization->id,
                'servico_id' => $authorization->servico_id,
                'valor' => $authorization->valor,
                'metodo' => $authorization->metodo->value,
                'status' => $authorization->status->value,
                'criado_em' => $authorization->criado_em?->utc()->toIso8601String(),
                'expira_em' => $authorization->expira_em?->utc()->toIso8601String(),
            ])
            ->values()
            ->all();

        return $this->paginated($paginator, $items);
    }

    public function dashboard(): JsonResponse
    {
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

    /**
     * @return array{0: int, 1: int}
     */
    private function paginationParams(Request $request): array
    {
        $perPage = (int) ($request->query('per_page', self::DEFAULT_PER_PAGE) ?: self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $page = (int) ($request->query('page', 1) ?: 1);
        $page = max(1, $page);

        return [$perPage, $page];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  list<array<string, mixed>>  $items
     */
    private function paginated(LengthAwarePaginator $paginator, array $items): JsonResponse
    {
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
}
