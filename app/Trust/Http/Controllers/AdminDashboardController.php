<?php

namespace App\Trust\Http\Controllers;

use App\Auth\Enums\TipoUsuario;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Trust\AdminContactLeakMetrics;
use App\Trust\Models\ContactPenaltyNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function show(Request $request, AdminContactLeakMetrics $metrics): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if ($usuario->tipo !== TipoUsuario::Admin) {
            return ApiResponse::error('Acesso negado.', 403);
        }

        return ApiResponse::success([
            'contact_leak' => $metrics->build(),
            'internal_notes' => ContactPenaltyNote::query()
                ->latest()
                ->limit(20)
                ->get(['usuario_id', 'attempts_in_window', 'nota', 'created_at']),
        ]);
    }
}
