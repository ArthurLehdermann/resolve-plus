<?php

namespace App\Requests\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Requests\Http\Requests\StoreTabelaPrecoRequest;
use App\Requests\Http\Requests\UpdateTabelaPrecoRequest;
use App\Requests\Http\Resources\TabelaPrecoResource;
use App\Requests\TabelaPreco;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class AdminPriceTableController extends Controller
{
    use AuthorizesRequests;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', TabelaPreco::class);

        $tabelas = TabelaPreco::query()
            ->orderBy('cidade')
            ->orderBy('categoria_id')
            ->get();

        return ApiResponse::success(TabelaPrecoResource::collection($tabelas)->resolve());
    }

    public function store(StoreTabelaPrecoRequest $request): JsonResponse
    {
        $this->authorize('create', TabelaPreco::class);

        try {
            $tabelaPreco = TabelaPreco::query()->create([
                'categoria_id' => $request->validated('categoria_id'),
                'cidade' => $request->validated('cidade'),
                'valor_min' => $request->validated('valor_min'),
                'valor_max' => $request->validated('valor_max'),
                'ativo' => $request->boolean('ativo', true),
            ]);
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::error(
                'Já existe uma tabela de preço ativa para esta categoria e cidade.',
                409,
            );
        }

        return ApiResponse::success((new TabelaPrecoResource($tabelaPreco))->resolve(), 201);
    }

    public function update(UpdateTabelaPrecoRequest $request, TabelaPreco $tabelaPreco): JsonResponse
    {
        $this->authorize('update', $tabelaPreco);

        try {
            $tabelaPreco->fill($request->validated())->save();
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::error(
                'Já existe uma tabela de preço ativa para esta categoria e cidade.',
                409,
            );
        }

        return ApiResponse::success((new TabelaPrecoResource($tabelaPreco->refresh()))->resolve());
    }
}
