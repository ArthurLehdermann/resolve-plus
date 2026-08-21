<?php

namespace App\Payments\Http;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDomainException;
use App\Payments\ReleasePayment;
use App\Payments\TipoPaymentEvent;
use App\Services\Servico;
use App\Support\ApiResponse;
use App\Support\IdempotentOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $query = PaymentAuthorization::query()->orderByDesc('criado_em');

        if ($usuario->tipo !== TipoUsuario::Admin) {
            $servicoIds = Servico::query()
                ->whereHas('proposta', function ($builder) use ($usuario): void {
                    $builder->where('profissional_id', $usuario->id)
                        ->orWhereHas('solicitacao', fn ($q) => $q->where('cliente_id', $usuario->id));
                })
                ->pluck('id');

            $query->whereIn('servico_id', $servicoIds);
        }

        $page = $query->paginate(20);

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (PaymentAuthorization $authorization): array => $this->toArray($authorization))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $authorization = PaymentAuthorization::query()->with('servico.proposta.solicitacao')->find($id);

        if ($authorization === null || ! $this->canView($usuario, $authorization)) {
            return ApiResponse::error('Pagamento não encontrado.', 404);
        }

        $split = $authorization->captureEvent()?->split;

        return ApiResponse::success([
            ...$this->toArray($authorization),
            'split' => $split === null ? null : [
                'valor_profissional' => $split->valor_profissional,
                'valor_plataforma' => $split->valor_plataforma,
                'aliquota_vigente' => (float) $split->aliquota_vigente,
            ],
        ]);
    }

    public function events(Request $request, string $id): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $authorization = PaymentAuthorization::query()->with('servico.proposta.solicitacao')->find($id);

        if ($authorization === null || ! $this->canView($usuario, $authorization)) {
            return ApiResponse::error('Pagamento não encontrado.', 404);
        }

        $events = $authorization->events()->orderBy('criado_em')->get();

        return ApiResponse::success(
            $events->map(fn ($event): array => [
                'id' => $event->id,
                'tipo' => $event->tipo->value,
                'payload' => $event->payload ?? [],
                'criado_em' => $event->criado_em->utc()->toIso8601String(),
            ])->values()->all(),
        );
    }

    public function release(ReleasePaymentRequest $request, string $id, IdempotentOperation $idempotency, ReleasePayment $release): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if ($usuario->tipo !== TipoUsuario::Admin) {
            return ApiResponse::error('Apenas administradores podem liberar pagamentos.', 403);
        }

        return $idempotency->run($request, "payments.release:{$id}", function () use ($request, $id, $usuario, $release): JsonResponse {
            $authorization = PaymentAuthorization::query()->with('servico')->find($id);

            if ($authorization === null) {
                return ApiResponse::error('Pagamento não encontrado.', 404);
            }

            if ($authorization->hasEvent(TipoPaymentEvent::Repassado)) {
                return ApiResponse::error('Pagamento já foi repassado.', 409);
            }

            try {
                $authorization = $release(
                    $authorization,
                    $usuario,
                    $request->string('justificativa')->toString(),
                    $request->ip(),
                );
            } catch (PaymentDomainException $exception) {
                $message = $exception->getMessage();
                $status = str_contains($message, 'INV-045') ? 409 : 422;

                return ApiResponse::error($message, $status);
            }

            return ApiResponse::success($this->toArray($authorization));
        });
    }

    private function canView(Usuario $usuario, PaymentAuthorization $authorization): bool
    {
        if ($usuario->tipo === TipoUsuario::Admin) {
            return true;
        }

        $servico = $authorization->servico;

        if ($servico === null) {
            return false;
        }

        return $servico->isClienteDono($usuario) || $servico->isProfissionalResponsavel($usuario);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(PaymentAuthorization $authorization): array
    {
        return [
            'id' => $authorization->id,
            'servico_id' => $authorization->servico_id,
            'valor' => $authorization->valor,
            'metodo' => $authorization->metodo->value,
            'status' => $authorization->status->value,
            'criado_em' => $authorization->criado_em->utc()->toIso8601String(),
            'expira_em' => $authorization->expira_em?->utc()->toIso8601String(),
        ];
    }
}
