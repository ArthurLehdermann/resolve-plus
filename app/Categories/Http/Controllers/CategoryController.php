<?php

namespace App\Categories\Http\Controllers;

use App\Categories\Http\Resources\CategoriaResource;
use App\Categories\Models\Categoria;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categorias = Categoria::query()
            ->ativas()
            ->orderBy('nome')
            ->get();

        return ApiResponse::success(
            CategoriaResource::collection($categorias)->resolve()
        );
    }

    public function show(Categoria $categoria): JsonResponse
    {
        abort_unless($categoria->ativo, 404);

        return ApiResponse::success(
            (new CategoriaResource($categoria))->resolve()
        );
    }
}
