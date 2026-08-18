<?php

namespace App\Services\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Actions\RescheduleAgenda;
use App\Services\Actions\StoreAgenda;
use App\Services\Agenda;
use App\Services\Exceptions\ServiceException;
use App\Services\Http\Requests\StoreScheduleRequest;
use App\Services\Http\Requests\UpdateScheduleRequest;
use App\Services\Http\Resources\AgendaResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->usuario($request);
        [$page, $perPage] = $this->pagination($request);

        $paginator = Agenda::query()
            ->where(function ($query) use ($usuario): void {
                $query->whereHas(
                    'servico.proposta',
                    fn ($proposta) => $proposta->where('profissional_id', $usuario->id),
                )->orWhereHas(
                    'servico.proposta.solicitacao',
                    fn ($solicitacao) => $solicitacao->where('cliente_id', $usuario->id),
                );
            })
            ->orderBy('data')
            ->orderBy('hora')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success(
            AgendaResource::collection($paginator->items())->resolve(),
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreScheduleRequest $request, StoreAgenda $action): JsonResponse
    {
        $agenda = $action($this->usuario($request), $request->validated());

        return ApiResponse::success(new AgendaResource($agenda), 201);
    }

    public function update(UpdateScheduleRequest $request, string $id, RescheduleAgenda $action): JsonResponse
    {
        $agenda = Agenda::query()->with('servico.proposta.solicitacao')->findOrFail($id);
        $agenda = $action($agenda, $this->usuario($request), $request->validated());

        return ApiResponse::success(new AgendaResource($agenda));
    }

    private function usuario(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw ServiceException::forbidden('Não autenticado.');
        }

        return $usuario;
    }

    /**
     * @return array{int, int}
     */
    private function pagination(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        return [$page, $perPage];
    }
}
