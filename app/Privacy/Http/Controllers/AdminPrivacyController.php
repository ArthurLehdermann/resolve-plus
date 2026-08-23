<?php

namespace App\Privacy\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Privacy\ContaComServicoEmAndamento;
use App\Privacy\ExcluirConta;
use App\Privacy\ExportarDadosPessoais;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Atendimento de pedido de titular que chega pelo encarregado (LGPD, art. 18).
 *
 * O usuário resolve sozinho no app; isto aqui é para quem escreve para o
 * e-mail de LGPD — inclusive quem já perdeu o acesso à conta e por isso não
 * consegue usar a tela do app. Passa pelos mesmos serviços da rota do titular:
 * o que o admin faz pelo painel tem exatamente o mesmo efeito.
 */
class AdminPrivacyController extends Controller
{
    /**
     * Localiza a conta antes de qualquer ação — o operador confirma que é a
     * pessoa certa olhando tipo, situação e data de criação.
     */
    public function search(Request $request): JsonResponse
    {
        $email = (string) $request->query('email', '');

        if (trim($email) === '') {
            return ApiResponse::error('Informe o e-mail do titular.', 422);
        }

        $usuario = Usuario::query()->comEmail($email)->first();

        if ($usuario === null) {
            return ApiResponse::error('Nenhuma conta com esse e-mail.', 404);
        }

        return ApiResponse::success($this->resumo($usuario));
    }

    public function export(Request $request, ExportarDadosPessoais $exportar): JsonResponse
    {
        $usuario = $this->usuarioDoPedido($request);

        if ($usuario === null) {
            return ApiResponse::error('Nenhuma conta com esse e-mail.', 404);
        }

        return ApiResponse::success([
            'titular' => $this->resumo($usuario),
            'dados' => $exportar->paraUsuario($usuario),
        ]);
    }

    public function destroyAccount(Request $request, ExcluirConta $excluir): JsonResponse
    {
        $usuario = $this->usuarioDoPedido($request);

        if ($usuario === null) {
            return ApiResponse::error('Nenhuma conta com esse e-mail.', 404);
        }

        try {
            $excluir->paraUsuario($usuario);
        } catch (ContaComServicoEmAndamento $e) {
            return ApiResponse::error($e->getMessage(), 409, code: 'SERVICO_EM_ANDAMENTO');
        }

        return ApiResponse::success(['mensagem' => 'Conta excluída e dados de identificação removidos.']);
    }

    private function usuarioDoPedido(Request $request): ?Usuario
    {
        $email = (string) $request->input('email', $request->query('email', ''));

        return Usuario::query()->comEmail($email)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo(Usuario $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nome' => $usuario->nome,
            'email' => $usuario->email,
            'tipo' => $usuario->tipo->value,
            'status' => $usuario->status->value,
            'criada_em' => $usuario->created_at?->toIso8601String(),
        ];
    }
}
