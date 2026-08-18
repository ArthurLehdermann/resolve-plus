<?php

namespace App\Services\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Actions\StoreMessage;
use App\Services\Exceptions\ServiceException;
use App\Services\Http\Requests\StoreMessageRequest;
use App\Services\Http\Resources\MensagemResource;
use App\Services\Servico;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, string $id): JsonResponse
    {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $usuario = $this->usuario($request);

        if (! $servico->isParticipante($usuario)) {
            throw ServiceException::forbidden(
                'Apenas o cliente ou o profissional deste serviço podem acessar o chat.',
            );
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = $servico->mensagens()
            ->orderBy('enviado_em')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success(
            MensagemResource::collection($paginator->items())->resolve(),
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreMessageRequest $request, string $id, StoreMessage $action): JsonResponse
    {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $mensagem = $action($servico, $this->usuario($request), $request->validated());

        return ApiResponse::success(new MensagemResource($mensagem), 201);
    }

    private function usuario(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw ServiceException::forbidden('Não autenticado.');
        }

        return $usuario;
    }
}
