<?php

namespace App\Privacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Privacy\ContaComServicoEmAndamento;
use App\Privacy\ExcluirConta;
use App\Privacy\ExportarDadosPessoais;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direitos do titular pelo próprio app, sem depender de e-mail para o
 * suporte: acesso/portabilidade e eliminação (LGPD, art. 18).
 */
class PrivacyController extends Controller
{
    public function export(Request $request, ExportarDadosPessoais $exportar): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        return ApiResponse::success($exportar->paraUsuario($usuario));
    }

    public function destroyAccount(Request $request, ExcluirConta $excluir): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        try {
            $excluir->paraUsuario($usuario);
        } catch (ContaComServicoEmAndamento $e) {
            // 409: não é erro de entrada nem falta de permissão — é estado do
            // negócio que impede a exclusão agora.
            return ApiResponse::error($e->getMessage(), 409, code: 'SERVICO_EM_ANDAMENTO');
        }

        return ApiResponse::success(['mensagem' => 'Conta excluída.']);
    }
}
