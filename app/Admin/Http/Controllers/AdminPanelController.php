<?php

namespace App\Admin\Http\Controllers;

use App\Auth\Http\Resources\UsuarioResource;
use App\Auth\Models\Usuario;
use App\Payments\PaymentAuthorization;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Http\Resources\DocumentoProfissionalResource;
use App\Services\Http\Resources\ServicoResource;
use App\Services\Servico;
use App\Support\ApiResponse;
use App\Trust\AdminContactLeakMetrics;
use App\Trust\Models\ContactPenaltyNote;
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
                'criado_em' => $authorization->criado_em->utc()->toIso8601String(),
                'expira_em' => $authorization->expira_em?->utc()->toIso8601String(),
            ])
            ->values()
            ->all();

        return $this->paginated($paginator, $items);
    }

    public function documents(Request $request): JsonResponse
    {
        [$perPage, $page] = $this->paginationParams($request);

        $status = $request->query('status');

        $query = DocumentoProfissional::query()->with('profissional');

        if (is_string($status) && $status !== '') {
            $query->where('status', StatusDocumentoProfissional::from($status)->value);
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn (DocumentoProfissional $documento): array => (new DocumentoProfissionalResource($documento))->toArray($request))
            ->values()
            ->all();

        return $this->paginated($paginator, $items);
    }

    public function dashboard(): JsonResponse
    {
        $metrics = app(AdminContactLeakMetrics::class)->build();

        $leakageMetrics = [
            'tentativas_pre_aceite' => $metrics['attempt_rate_pre_acceptance'],
            'tentativas_pos_aceite' => $metrics['attempt_rate_post_acceptance'],
            'taxa_conclusao_pos_tentativa' => $metrics['post_attempt_completion_rate'],
        ];

        return ApiResponse::success([
            'general_indicators' => [
                'total_usuarios' => Usuario::query()->count(),
            ],
            'leakage_metrics' => $leakageMetrics,
            // Mantém compatibilidade com AntiDisintermediationTest e com o documento
            // de mecanismo de vazamento (03/04 e seção 4 do admin dashboard).
            'contact_leak' => $metrics,
            'internal_notes' => ContactPenaltyNote::query()
                ->latest()
                ->limit(20)
                ->get(['usuario_id', 'attempts_in_window', 'nota', 'created_at']),
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
