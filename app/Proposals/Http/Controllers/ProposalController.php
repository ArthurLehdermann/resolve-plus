<?php

namespace App\Proposals\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Proposals\Actions\AcceptProposal;
use App\Proposals\Actions\StoreProposal;
use App\Proposals\Actions\WithdrawProposal;
use App\Proposals\Exceptions\ProposalException;
use App\Proposals\Http\Requests\StoreProposalRequest;
use App\Proposals\Http\Resources\ProposalResource;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProposalController extends Controller
{
    public function index(Request $request, string $id): JsonResponse
    {
        $solicitacao = Solicitacao::query()->findOrFail($id);
        $usuario = $this->usuario($request);

        if ($solicitacao->cliente_id !== $usuario->id) {
            throw ProposalException::forbidden(
                'Apenas o cliente dono da solicitação pode listar as propostas.',
            );
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = $solicitacao->propostas()
            ->with('profissional')
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success(
            ProposalResource::collection($paginator->items())->resolve(),
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreProposalRequest $request, string $id, StoreProposal $action): JsonResponse
    {
        $solicitacao = Solicitacao::query()->findOrFail($id);
        $proposta = $action(
            $solicitacao,
            $this->usuario($request),
            $request->validated(),
        );

        return ApiResponse::success(new ProposalResource($proposta), 201);
    }

    public function accept(Request $request, string $id, AcceptProposal $action): JsonResponse
    {
        $this->assertIdempotencyKey($request);

        $proposta = Proposta::query()->with(['solicitacao', 'profissional', 'servico'])->findOrFail($id);
        $result = $action($proposta, $this->usuario($request));

        $result['proposta']->setRelation('servico', $result['servico']);

        return ApiResponse::success(new ProposalResource($result['proposta']), 201);
    }

    public function withdraw(Request $request, string $id, WithdrawProposal $action): JsonResponse
    {
        $proposta = Proposta::query()->with('profissional')->findOrFail($id);
        $proposta = $action($proposta, $this->usuario($request));

        return ApiResponse::success(new ProposalResource($proposta));
    }

    private function usuario(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw ProposalException::forbidden('Não autenticado.');
        }

        return $usuario;
    }

    private function assertIdempotencyKey(Request $request): void
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '' || ! Str::isUuid($key)) {
            throw ProposalException::unprocessable(
                'Header Idempotency-Key é obrigatório e deve ser um UUID.',
            );
        }
    }
}
