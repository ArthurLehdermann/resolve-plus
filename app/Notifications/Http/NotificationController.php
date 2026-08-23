<?php

namespace App\Notifications\Http;

use App\Http\Controllers\Controller;
use App\Notifications\Notificacao;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Notificacao::query()
            ->where('usuario_id', $usuario->id)
            ->orderByDesc('criado_em');

        if ($request->boolean('unread')) {
            $query->whereNull('lida_em');
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $naoLidas = Notificacao::query()
            ->where('usuario_id', $usuario->id)
            ->whereNull('lida_em')
            ->count();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn (Notificacao $notificacao): array => $this->toArray($notificacao))->values()->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / max($perPage, 1))),
            ],
            // Vai junto porque é o número do badge: a tela não deveria precisar
            // de uma segunda chamada só para saber se tem bolinha vermelha.
            'unread_count' => $naoLidas,
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $notificacao = Notificacao::query()->find($id);

        // 404 e não 403 de propósito: quem não é dono não descobre que o id existe.
        if ($notificacao === null || $notificacao->usuario_id !== $usuario->id) {
            return ApiResponse::error('Notificação não encontrada.', 404);
        }

        // Marcar de novo não move a data: a primeira leitura é a que vale.
        if (! $notificacao->isLida()) {
            $notificacao->lida_em = now();
            $notificacao->save();
        }

        return ApiResponse::success($this->toArray($notificacao->refresh()));
    }

    public function readAll(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $marcadas = Notificacao::query()
            ->where('usuario_id', $usuario->id)
            ->whereNull('lida_em')
            ->update(['lida_em' => now(), 'atualizado_em' => now()]);

        return ApiResponse::success(['marcadas' => $marcadas]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Notificacao $notificacao): array
    {
        return [
            'id' => $notificacao->id,
            'tipo' => $notificacao->tipo->value,
            'titulo' => $notificacao->titulo,
            'corpo' => $notificacao->corpo,
            'dados' => $notificacao->dados ?? [],
            'lida_em' => $notificacao->lida_em?->utc()->toIso8601String(),
            'criado_em' => $notificacao->criado_em->utc()->toIso8601String(),
        ];
    }
}
