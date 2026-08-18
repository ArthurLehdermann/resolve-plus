<?php

namespace App\Ratings\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Ratings\Actions\RegisterRating;
use App\Ratings\Exceptions\RatingException;
use App\Ratings\Http\Requests\StoreRatingRequest;
use App\Ratings\Http\Resources\AvaliacaoResource;
use App\Services\Servico;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RatingController extends Controller
{
    public function store(StoreRatingRequest $request, string $id, RegisterRating $action): JsonResponse
    {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $avaliacao = $action($servico, $this->usuario($request), $request->nota(), $request->comentario());

        return ApiResponse::success(new AvaliacaoResource($avaliacao), 201);
    }

    private function usuario(StoreRatingRequest $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw RatingException::forbidden('Não autenticado.');
        }

        return $usuario;
    }
}
