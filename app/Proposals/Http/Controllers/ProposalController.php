<?php

namespace App\Proposals\Http\Controllers;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Payments\Gateway\GatewayException;
use App\Payments\PaymentDomainException;
use App\Proposals\Actions\AcceptProposal;
use App\Proposals\Actions\StoreProposal;
use App\Proposals\Actions\WithdrawProposal;
use App\Proposals\Exceptions\ProposalException;
use App\Proposals\Http\Requests\AcceptProposalRequest;
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
        $usuario = $this->usuario($request);
        $this->assertProfissionalAtivo($usuario);

        $solicitacao = Solicitacao::query()->findOrFail($id);
        $proposta = $action(
            $solicitacao,
            $usuario,
            $request->validated(),
        );

        return ApiResponse::success(new ProposalResource($proposta), 201);
    }

    public function accept(AcceptProposalRequest $request, string $id, AcceptProposal $action): JsonResponse
    {
        $this->assertIdempotencyKey($request);

        $proposta = Proposta::query()->with(['solicitacao', 'profissional', 'servico'])->findOrFail($id);

        try {
            $result = $action(
                $proposta,
                $this->usuario($request),
                $request->metodoPagamento(),
                $request->creditCardToken(),
            );
        } catch (PaymentDomainException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        } catch (GatewayException $exception) {
            return ApiResponse::error('Gateway de pagamento recusou a cobrança: '.$exception->getMessage(), 502);
        }

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

    private function assertProfissionalAtivo(Usuario $usuario): void
    {
        if ($usuario->tipo !== TipoUsuario::Profissional || $usuario->status !== StatusConta::Ativa) {
            throw ProposalException::forbidden(
                'Apenas profissionais com conta ativa podem enviar propostas.',
            );
        }
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
