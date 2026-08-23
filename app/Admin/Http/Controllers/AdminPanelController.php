<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Configuracao;
use App\Auth\Http\Resources\UsuarioResource;
use App\Auth\Models\Usuario;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Http\Resources\DocumentoProfissionalResource;
use App\Proposals\Proposta;
use App\Services\Http\Resources\ServicoResource;
use App\Services\Servico;
use App\Support\ApiResponse;
use App\Trust\AdminContactLeakMetrics;
use App\Trust\Models\ContactPenaltyNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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
            // ServicoResource resolve cliente e profissional pela proposta (ou
            // pela garantia de origem, na revisita): sem eager loading a
            // listagem vira N+1.
            ->with(['proposta.solicitacao', 'garantiaOrigem.servico.proposta.solicitacao'])
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

    /**
     * Fila de mediação do Admin (foundation/03-cancellation-rules.md,
     * "Resolução de Em Contestação"). Traz junto o contexto que a decisão
     * exige — partes, escopo, valor e o que o profissional registrou na
     * conclusão —, porque sem isso o painel só teria um id de serviço.
     */
    public function disputes(Request $request): JsonResponse
    {
        [$perPage, $page] = $this->paginationParams($request);

        $status = $request->query('status');
        $tipo = $request->query('tipo');

        $query = PaymentDispute::query()->with([
            'resolvidaPor',
            'servico.authorizations',
            'servico.proposta.profissional',
            'servico.proposta.solicitacao.categoria',
            'servico.proposta.solicitacao.cliente',
            'servico.proposta.solicitacao.property',
            'servico.garantiaOrigem.servico.proposta.profissional',
            'servico.garantiaOrigem.servico.proposta.solicitacao.categoria',
            'servico.garantiaOrigem.servico.proposta.solicitacao.cliente',
            'servico.garantiaOrigem.servico.proposta.solicitacao.property',
        ]);

        if (is_string($status) && $status !== '') {
            $filtro = StatusPaymentDispute::tryFrom($status);

            if ($filtro === null) {
                return ApiResponse::error('Status de disputa inválido.', 422);
            }

            $query->where('status', $filtro->value);
        }

        if (is_string($tipo) && $tipo !== '') {
            $filtroTipo = TipoPaymentDispute::tryFrom($tipo);

            if ($filtroTipo === null) {
                return ApiResponse::error('Tipo de disputa inválido.', 422);
            }

            $query->where('tipo', $filtroTipo->value);
        }

        $paginator = $query
            // Aberta primeiro: a fila do Admin é o que ainda trava um serviço.
            ->orderByRaw("CASE WHEN status = 'ABERTA' THEN 0 ELSE 1 END")
            ->orderByDesc('aberta_em')
            ->paginate($perPage, ['*'], 'page', $page);

        $mediacaoDias = $this->diasDeMediacao();

        $items = $paginator->getCollection()
            ->map(fn (PaymentDispute $dispute): array => $this->disputePayload($dispute, $mediacaoDias))
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
     * @return array<string, mixed>
     */
    private function disputePayload(PaymentDispute $dispute, ?int $mediacaoDias): array
    {
        $servico = $dispute->servico;
        $proposta = $servico === null ? null : $this->propostaDoServico($servico);
        $solicitacao = $proposta?->solicitacao;

        /** @var PaymentAuthorization|null $authorization */
        $authorization = $servico?->authorizations->sortByDesc('criado_em')->first();

        $prazo = $dispute->status === StatusPaymentDispute::Aberta && $mediacaoDias !== null
            ? $dispute->aberta_em?->addDays($mediacaoDias)
            : null;

        return [
            'id' => $dispute->id,
            'tipo' => $dispute->tipo->value,
            'status' => $dispute->status->value,
            'motivo' => $dispute->motivo,
            'aberta_em' => $dispute->aberta_em?->utc()->toIso8601String(),
            'prazo_em' => $prazo?->utc()->toIso8601String(),
            'resolvida_em' => $dispute->resolvida_em?->utc()->toIso8601String(),
            'resultado' => $dispute->resultado?->value,
            'justificativa' => $dispute->justificativa,
            'resolvida_por' => $dispute->resolvidaPor === null ? null : [
                'id' => $dispute->resolvidaPor->id,
                'nome' => $dispute->resolvidaPor->nome,
            ],
            'servico' => $servico === null ? null : [
                'id' => $servico->id,
                'status' => $servico->status->value,
                'notas' => $servico->notas,
                'fotos' => $servico->fotos ?? [],
                'valor' => $proposta?->valor,
                'categoria' => $solicitacao?->categoria?->nome,
                'descricao' => $solicitacao?->descricao,
                'cidade' => $solicitacao?->property?->cidade,
                'cliente' => $solicitacao?->cliente === null ? null : [
                    'id' => $solicitacao->cliente->id,
                    'nome' => $solicitacao->cliente->nome,
                    'email' => $solicitacao->cliente->email,
                ],
                'profissional' => $proposta?->profissional === null ? null : [
                    'id' => $proposta->profissional->id,
                    'nome' => $proposta->profissional->nome,
                    'email' => $proposta->profissional->email,
                ],
                'pagamento' => $authorization === null ? null : [
                    'id' => $authorization->id,
                    'valor' => $authorization->valor,
                    'metodo' => $authorization->metodo->value,
                    'status' => $authorization->status->value,
                ],
            ],
        ];
    }

    /**
     * Revisita de garantia (INV-033) não tem proposta própria: escopo, valor e
     * partes são os do serviço de origem.
     */
    private function propostaDoServico(Servico $servico): ?Proposta
    {
        if ($servico->isRevisitaGarantia()) {
            return $servico->garantiaOrigem?->servico?->proposta;
        }

        return $servico->proposta;
    }

    /**
     * Prazo do timeout automático (ResolveExpiredDisputes). Sem a configuração
     * a listagem continua de pé, só não mostra a data-limite.
     */
    private function diasDeMediacao(): ?int
    {
        try {
            return Configuracao::inteiro('DISPUTE_MEDIATION_DAYS');
        } catch (RuntimeException) {
            return null;
        }
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
