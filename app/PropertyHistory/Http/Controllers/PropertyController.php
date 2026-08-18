<?php

namespace App\PropertyHistory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\PropertyHistory\ChaveEndereco;
use App\PropertyHistory\Http\Requests\StorePropertyRequest;
use App\PropertyHistory\Http\Requests\UpdatePropertyRequest;
use App\PropertyHistory\Http\Resources\PropertyResource;
use App\PropertyHistory\Property;
use App\PropertyHistory\PropertyOwnership;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $properties = Property::query()
            ->whereHas(
                'currentOwnership',
                fn ($query) => $query->where('cliente_id', $usuario->id),
            )
            ->orderByDesc('criado_em')
            ->get();

        return ApiResponse::success(PropertyResource::collection($properties)->resolve($request));
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $payload = $request->safe()->only([
            'cep',
            'logradouro',
            'numero',
            'complemento',
            'bairro',
            'cidade',
            'estado',
            'latitude',
            'longitude',
            'apelido',
        ]);

        $chave = ChaveEndereco::from(
            (string) $payload['cep'],
            (string) $payload['numero'],
            isset($payload['complemento']) ? (string) $payload['complemento'] : null,
        );

        $duplicate = $this->duplicateResponse($chave);
        if ($duplicate !== null) {
            return $duplicate;
        }

        try {
            $property = DB::transaction(function () use ($payload, $usuario): Property {
                $property = Property::query()->create($payload);

                PropertyOwnership::query()->create([
                    'property_id' => $property->id,
                    'cliente_id' => $usuario->id,
                    'desde' => now(),
                    'ate' => null,
                ]);

                return $property;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $duplicate = $this->duplicateResponse($chave);
            if ($duplicate !== null) {
                return $duplicate;
            }

            throw $exception;
        }

        return ApiResponse::success(new PropertyResource($property->refresh()), 201);
    }

    public function update(UpdatePropertyRequest $request, string $id): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $property = Property::query()->with('currentOwnership')->find($id);

        if ($property === null) {
            return ApiResponse::error('Imóvel não encontrado.', 404);
        }

        if (! $property->isCurrentOwner($usuario)) {
            return ApiResponse::error('Somente o dono vigente pode editar o imóvel.', 403);
        }

        $payload = $request->safe()->only([
            'cep',
            'logradouro',
            'numero',
            'complemento',
            'bairro',
            'cidade',
            'estado',
            'latitude',
            'longitude',
            'apelido',
        ]);

        $property->fill($payload);

        $chave = ChaveEndereco::from(
            (string) $property->cep,
            (string) $property->numero,
            $property->complemento,
        );

        $duplicate = $this->duplicateResponse($chave, $property->id);
        if ($duplicate !== null) {
            return $duplicate;
        }

        try {
            $property->save();
        } catch (UniqueConstraintViolationException $exception) {
            $duplicate = $this->duplicateResponse($chave, $property->id);
            if ($duplicate !== null) {
                return $duplicate;
            }

            throw $exception;
        }

        return ApiResponse::success(new PropertyResource($property->refresh()));
    }

    private function duplicateResponse(string $chave, ?string $ignoreId = null): ?JsonResponse
    {
        $existing = Property::query()
            ->where('chave_endereco', $chave)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->first();

        if ($existing === null) {
            return null;
        }

        return ApiResponse::error(
            'Já existe um imóvel cadastrado neste endereço.',
            409,
            ['property_id' => $existing->id],
        );
    }
}
