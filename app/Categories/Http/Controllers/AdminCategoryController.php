<?php

namespace App\Categories\Http\Controllers;

use App\Categories\Http\Requests\StoreCategoriaRequest;
use App\Categories\Http\Requests\UpdateCategoriaRequest;
use App\Categories\Http\Resources\CategoriaResource;
use App\Categories\Models\Categoria;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class AdminCategoryController extends Controller
{
    use AuthorizesRequests;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Categoria::class);

        $categorias = Categoria::query()
            ->orderBy('nome')
            ->get();

        return ApiResponse::success(
            CategoriaResource::collection($categorias)->resolve()
        );
    }

    public function show(Categoria $categoria): JsonResponse
    {
        $this->authorize('view', $categoria);

        return ApiResponse::success(
            (new CategoriaResource($categoria))->resolve()
        );
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        $this->authorize('create', Categoria::class);

        $categoria = Categoria::query()->create([
            'codigo' => $request->string('codigo')->toString(),
            'nome' => $request->string('nome')->toString(),
            'descricao' => $request->input('descricao'),
            'ativo' => $request->boolean('ativo', true),
            'template_escopo' => $request->input('template_escopo'),
        ]);

        return ApiResponse::success(
            (new CategoriaResource($categoria))->resolve(),
            201
        );
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria): JsonResponse
    {
        $this->authorize('update', $categoria);

        $categoria->fill($request->validated());
        $categoria->save();

        return ApiResponse::success(
            (new CategoriaResource($categoria))->resolve()
        );
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        $this->authorize('delete', $categoria);

        $categoria->delete();

        return ApiResponse::success();
    }
}
