<?php

namespace App\Services\Http\Controllers;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Actions\ApproveService;
use App\Services\Actions\CancelService;
use App\Services\Actions\ContestService;
use App\Services\Actions\FinishService;
use App\Services\Actions\OpenDispute;
use App\Services\Actions\StartService;
use App\Services\Exceptions\ServiceException;
use App\Services\Http\Requests\CancelServiceRequest;
use App\Services\Http\Requests\ContestServiceRequest;
use App\Services\Http\Requests\FinishServiceRequest;
use App\Services\Http\Requests\OpenDisputeRequest;
use App\Services\Http\Resources\ServicoResource;
use App\Services\Servico;
use App\Services\StatusServico;
use App\Support\ApiResponse;
use App\Support\IdempotentOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Relações que as telas de lista e detalhe (F6/F7) precisam para montar o
     * card do serviço sem uma segunda rodada de chamadas.
     *
     * @var list<string>
     */
    private const CONTEXTO = [
        'proposta.solicitacao',
        'proposta.profissional',
        'agenda',
        'authorizations',
        'avaliacoes',
        'garantia',
        'garantiaOrigem.servico.proposta.solicitacao',
    ];

    /**
     * Serviços em que o usuário é cliente ou profissional. Inclui revisita de
     * garantia (INV-033), que não tem proposta própria e amarra no serviço de
     * origem via `garantia_origem_id`.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->usuario($request);

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Servico::query()
            ->with(self::CONTEXTO)
            ->where(fn ($builder) => $this->doUsuario($builder, $usuario))
            ->orderByDesc('created_at');

        $status = $request->string('status')->toString();

        if ($status !== '') {
            $caso = StatusServico::tryFrom($status);

            if ($caso === null) {
                throw ServiceException::unprocessable("Status inválido: {$status}.");
            }

            $query->where('status', $caso);
        }

        $total = $query->count();
        $servicos = $query->forPage($page, $perPage)->get();

        return ApiResponse::paginated(
            ServicoResource::collection($servicos)->resolve($request),
            $page,
            $perPage,
            $total,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $usuario = $this->usuario($request);
        $servico = Servico::query()->with(self::CONTEXTO)->findOrFail($id);

        if (! $servico->isParticipante($usuario) && $usuario->tipo !== TipoUsuario::Admin) {
            throw ServiceException::forbidden('Sem permissão para visualizar este serviço.');
        }

        return ApiResponse::success(new ServicoResource($servico));
    }

    public function start(Request $request, string $id, StartService $action): JsonResponse
    {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $servico = $action($servico, $this->usuario($request));

        return ApiResponse::success(new ServicoResource($servico));
    }

    public function finish(FinishServiceRequest $request, string $id, FinishService $action): JsonResponse
    {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $servico = $action($servico, $this->usuario($request), $request->notes(), $request->photos());

        return ApiResponse::success(new ServicoResource($servico));
    }

    public function approve(
        Request $request,
        string $id,
        ApproveService $action,
        IdempotentOperation $idempotency,
    ): JsonResponse {
        return $idempotency->run($request, "services.approve:{$id}", function () use ($request, $id, $action): JsonResponse {
            $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
            $servico = $action->byCliente($servico, $this->usuario($request));

            return ApiResponse::success(new ServicoResource($servico));
        });
    }

    public function contest(
        ContestServiceRequest $request,
        string $id,
        ContestService $action,
        IdempotentOperation $idempotency,
    ): JsonResponse {
        return $idempotency->run($request, "services.contest:{$id}", function () use ($request, $id, $action): JsonResponse {
            $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
            $servico = $action($servico, $this->usuario($request), $request->motivo());

            return ApiResponse::success(new ServicoResource($servico));
        });
    }

    public function cancel(
        CancelServiceRequest $request,
        string $id,
        CancelService $action,
        IdempotentOperation $idempotency,
    ): JsonResponse {
        return $idempotency->run($request, "services.cancel:{$id}", function () use ($request, $id, $action): JsonResponse {
            $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
            $result = $action($servico, $this->usuario($request), $request->motivo());

            $payload = [
                'servico' => new ServicoResource($result['servico']),
            ];

            if ($result['multa'] !== null) {
                $payload['multa'] = $result['multa'];
            }

            if ($result['dispute'] !== null) {
                $payload['dispute'] = [
                    'id' => $result['dispute']->id,
                    'tipo' => $result['dispute']->tipo->value,
                    'status' => $result['dispute']->status->value,
                ];
            }

            return ApiResponse::success($payload);
        });
    }

    public function openDispute(
        OpenDisputeRequest $request,
        string $id,
        OpenDispute $action,
    ): JsonResponse {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $usuario = $this->usuario($request);

        if (! $servico->isParticipante($usuario)) {
            throw ServiceException::forbidden(
                'Apenas cliente ou profissional do serviço podem abrir disputa.',
            );
        }

        $dispute = $action($servico, $usuario, $request->tipo(), $request->motivo());

        return ApiResponse::success([
            'id' => $dispute->id,
            'tipo' => $dispute->tipo->value,
            'status' => $dispute->status->value,
            'servico_id' => $dispute->servico_id,
        ], 201);
    }

    /**
     * Cliente e profissional chegam por dois caminhos: pela proposta do próprio
     * serviço ou, na revisita de garantia, pela proposta do serviço de origem.
     *
     * @param  Builder<Servico>  $builder
     */
    private function doUsuario(Builder $builder, Usuario $usuario): void
    {
        $participante = fn ($proposta) => $proposta
            ->where('profissional_id', $usuario->id)
            ->orWhereHas('solicitacao', fn ($solicitacao) => $solicitacao->where('cliente_id', $usuario->id));

        $builder
            ->whereHas('proposta', $participante)
            ->orWhereHas('garantiaOrigem.servico.proposta', $participante);
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
