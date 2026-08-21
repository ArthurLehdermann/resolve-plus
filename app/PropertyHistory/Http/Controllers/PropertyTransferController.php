<?php

namespace App\PropertyHistory\Http\Controllers;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\PropertyHistory\Http\Requests\InitiateTransferRequest;
use App\PropertyHistory\Http\Resources\PropertyOwnershipTransferResource;
use App\PropertyHistory\Property;
use App\PropertyHistory\PropertyOwnership;
use App\PropertyHistory\PropertyOwnershipTransfer;
use App\PropertyHistory\StatusPropertyOwnershipTransfer;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $transfers = PropertyOwnershipTransfer::query()
            ->where('status', StatusPropertyOwnershipTransfer::Pendente)
            ->where(function ($query) use ($usuario): void {
                $query->where('de_cliente_id', $usuario->id)
                    ->orWhere('para_cliente_id', $usuario->id)
                    ->orWhere(function ($destination) use ($usuario): void {
                        $destination->whereNull('para_cliente_id')
                            ->whereRaw('LOWER(para_email) = ?', [mb_strtolower($usuario->email)]);
                    });
            })
            ->orderByDesc('criado_em')
            ->get();

        return ApiResponse::success(
            PropertyOwnershipTransferResource::collection($transfers)->resolve($request),
        );
    }

    public function initiate(InitiateTransferRequest $request, string $id): JsonResponse
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
            return ApiResponse::error('Somente o dono vigente pode iniciar a transferência.', 403);
        }

        $destino = $this->resolveTransferDestination($request);
        if ($destino instanceof JsonResponse) {
            return $destino;
        }

        if ($destino['para_cliente_id'] === $usuario->id
            || strcasecmp($destino['para_email'], $usuario->email) === 0) {
            return ApiResponse::error('Não é possível transferir o imóvel para o dono atual.', 422);
        }

        $pendente = PropertyOwnershipTransfer::query()
            ->where('property_id', $property->id)
            ->where('status', StatusPropertyOwnershipTransfer::Pendente)
            ->exists();

        if ($pendente) {
            return ApiResponse::error('Já existe uma transferência pendente para este imóvel.', 409);
        }

        $transfer = PropertyOwnershipTransfer::query()->create([
            'property_id' => $property->id,
            'de_cliente_id' => $usuario->id,
            'para_cliente_id' => $destino['para_cliente_id'],
            'para_email' => $destino['para_email'],
            'status' => StatusPropertyOwnershipTransfer::Pendente,
            'expira_em' => now()->addDays(PropertyOwnershipTransfer::EXPIRATION_DAYS),
        ]);

        return ApiResponse::success(new PropertyOwnershipTransferResource($transfer), 201);
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $transfer = PropertyOwnershipTransfer::query()->find($id);

        if ($transfer === null) {
            return ApiResponse::error('Transferência não encontrada.', 404);
        }

        $blocked = $this->ensurePendingDestination($transfer, $usuario);
        if ($blocked !== null) {
            return $blocked;
        }

        $result = DB::transaction(function () use ($transfer, $usuario): array {
            $locked = PropertyOwnershipTransfer::query()
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== StatusPropertyOwnershipTransfer::Pendente) {
                return ['ok' => false, 'transfer' => $locked];
            }

            $current = PropertyOwnership::query()
                ->where('property_id', $locked->property_id)
                ->whereNull('ate')
                ->lockForUpdate()
                ->first();

            if ($current === null || $current->cliente_id !== $locked->de_cliente_id) {
                return ['ok' => false, 'transfer' => $locked];
            }

            $now = CarbonImmutable::now();
            $current->ate = $now;
            $current->save();

            PropertyOwnership::query()->create([
                'property_id' => $locked->property_id,
                'cliente_id' => $usuario->id,
                'desde' => $now,
                'ate' => null,
            ]);

            $locked->para_cliente_id = $usuario->id;
            $locked->para_email = $usuario->email;
            $locked->status = StatusPropertyOwnershipTransfer::Aceito;
            $locked->save();

            return ['ok' => true, 'transfer' => $locked];
        });

        if ($result['ok'] !== true) {
            return ApiResponse::error('Não foi possível concluir a transferência de posse.', 409);
        }

        return ApiResponse::success(new PropertyOwnershipTransferResource($result['transfer']->refresh()));
    }

    public function decline(Request $request, string $id): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $transfer = PropertyOwnershipTransfer::query()->find($id);

        if ($transfer === null) {
            return ApiResponse::error('Transferência não encontrada.', 404);
        }

        $blocked = $this->ensurePendingDestination($transfer, $usuario);
        if ($blocked !== null) {
            return $blocked;
        }

        $transfer->status = StatusPropertyOwnershipTransfer::Recusado;
        $transfer->save();

        return ApiResponse::success(new PropertyOwnershipTransferResource($transfer->refresh()));
    }

    private function ensurePendingDestination(PropertyOwnershipTransfer $transfer, Usuario $usuario): ?JsonResponse
    {
        if ($transfer->isExpired() && $transfer->status === StatusPropertyOwnershipTransfer::Pendente) {
            $transfer->status = StatusPropertyOwnershipTransfer::Expirado;
            $transfer->save();
        }

        if ($transfer->status !== StatusPropertyOwnershipTransfer::Pendente) {
            return ApiResponse::error('A transferência não está pendente.', 409);
        }

        if (! $transfer->isDestination($usuario)) {
            return ApiResponse::error('Somente o destinatário pode executar esta ação.', 403);
        }

        return null;
    }

    /**
     * @return array{para_cliente_id: ?string, para_email: string}|JsonResponse
     */
    private function resolveTransferDestination(InitiateTransferRequest $request): array|JsonResponse
    {
        $paraClienteId = $request->input('para_cliente_id');
        $paraEmail = $request->input('para_email');

        $destino = null;

        if (is_string($paraClienteId) && $paraClienteId !== '') {
            $destino = Usuario::query()->find($paraClienteId);

            if ($destino === null) {
                return ApiResponse::error('Destinatário não encontrado.', 422);
            }

            if (is_string($paraEmail) && $paraEmail !== '' && strcasecmp($paraEmail, $destino->email) !== 0) {
                return ApiResponse::error('para_email não corresponde ao para_cliente_id informado.', 422);
            }

            $paraEmail = $destino->email;
        } elseif (is_string($paraEmail) && $paraEmail !== '') {
            $destino = Usuario::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($paraEmail)])
                ->first();
            $paraClienteId = $destino?->id;
            // Falso positivo do Larastan: $destino vem de ->first() (pode ser
            // null de verdade, é busca por e-mail sem garantia de match).
            // Achado de auditoria 2026-08-21, ver phpstan.neon.
            // @phpstan-ignore-next-line nullsafe.neverNull
            $paraEmail = $destino?->email ?? $paraEmail;
        }

        if (! is_string($paraEmail) || $paraEmail === '') {
            return ApiResponse::error('Informe para_cliente_id ou para_email.', 422);
        }

        if ($destino !== null && $destino->tipo !== TipoUsuario::Cliente) {
            return ApiResponse::error('A posse só pode ser transferida para um cliente.', 422);
        }

        return [
            'para_cliente_id' => is_string($paraClienteId) ? $paraClienteId : null,
            'para_email' => $paraEmail,
        ];
    }
}
