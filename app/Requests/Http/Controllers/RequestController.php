<?php

namespace App\Requests\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Requests\Events\SolicitacaoCriada;
use App\Requests\FotoSolicitacao;
use App\Requests\Http\Requests\StoreSolicitacaoRequest;
use App\Requests\Http\Requests\UpdateSolicitacaoRequest;
use App\Requests\Http\Requests\UploadSolicitacaoPhotoRequest;
use App\Requests\Http\Resources\FotoSolicitacaoResource;
use App\Requests\Http\Resources\SolicitacaoResource;
use App\Requests\Jobs\ProcessSolicitacaoPhotoJob;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Solicitacao::query()
            ->with('fotos')
            ->where('cliente_id', $usuario->id)
            ->orderByDesc('criado_em');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $categoria = $request->input('categoria') ?? $request->input('category');
        if (is_string($categoria) && $categoria !== '') {
            $query->where('categoria_id', $categoria);
        }

        if ($request->filled('data')) {
            $query->whereDate('data_desejada', (string) $request->input('data'));
        }

        $total = $query->count();
        $items = $query
            ->forPage($page, $perPage)
            ->get();

        return ApiResponse::paginated(
            SolicitacaoResource::collection($items)->resolve($request),
            $page,
            $perPage,
            $total,
        );
    }

    public function store(StoreSolicitacaoRequest $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $solicitacao = Solicitacao::query()->create([
            'cliente_id' => $usuario->id,
            'categoria_id' => $request->validated('category_id'),
            'property_id' => $request->validated('property_id'),
            'descricao' => $request->validated('description'),
            'escopo' => $request->validated('scope'),
            'status' => StatusSolicitacao::Criada,
            'data_desejada' => $request->validated('desired_date'),
        ]);

        $solicitacao->forceFill([
            'status' => StatusSolicitacao::Aberta,
        ])->save();

        SolicitacaoCriada::dispatch($solicitacao);

        return ApiResponse::success(
            (new SolicitacaoResource($solicitacao->load('fotos')))->resolve($request),
            201,
        );
    }

    public function show(Request $request, Solicitacao $solicitacao): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if (! $solicitacao->ownedBy($usuario)) {
            return ApiResponse::error('Solicitação não encontrada.', 404);
        }

        return ApiResponse::success(
            (new SolicitacaoResource($solicitacao->load('fotos')))->resolve($request),
        );
    }

    public function update(UpdateSolicitacaoRequest $request, Solicitacao $solicitacao): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if (! $solicitacao->ownedBy($usuario)) {
            return ApiResponse::error('Solicitação não encontrada.', 404);
        }

        if (! $solicitacao->status->isEditable()) {
            return ApiResponse::error('Solicitação não pode ser editada neste status.', 409);
        }

        $payload = $request->safe()->only([
            'property_id',
            'category_id',
            'description',
            'scope',
            'desired_date',
        ]);

        if (array_key_exists('scope', $payload) && $this->escopoMudou($solicitacao, $payload['scope'])) {
            if ($solicitacao->hasPropostas()) {
                return ApiResponse::error(
                    'Escopo não pode ser alterado após existirem propostas.',
                    409,
                );
            }
        }

        $solicitacao->fill([
            'property_id' => $payload['property_id'] ?? $solicitacao->property_id,
            'categoria_id' => $payload['category_id'] ?? $solicitacao->categoria_id,
            'descricao' => $payload['description'] ?? $solicitacao->descricao,
            'escopo' => $payload['scope'] ?? $solicitacao->escopo,
            'data_desejada' => array_key_exists('desired_date', $payload)
                ? $payload['desired_date']
                : $solicitacao->data_desejada,
        ])->save();

        return ApiResponse::success(
            (new SolicitacaoResource($solicitacao->refresh()->load('fotos')))->resolve($request),
        );
    }

    public function destroy(Request $request, Solicitacao $solicitacao): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if (! $solicitacao->ownedBy($usuario)) {
            return ApiResponse::error('Solicitação não encontrada.', 404);
        }

        if (! $solicitacao->status->isCancellable()) {
            return ApiResponse::error('Solicitação não pode ser cancelada neste status.', 409);
        }

        $solicitacao->forceFill([
            'status' => StatusSolicitacao::Cancelada,
        ])->save();

        return ApiResponse::success(
            (new SolicitacaoResource($solicitacao->refresh()->load('fotos')))->resolve($request),
        );
    }

    public function uploadPhoto(UploadSolicitacaoPhotoRequest $request, Solicitacao $solicitacao): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if (! $solicitacao->ownedBy($usuario)) {
            return ApiResponse::error('Solicitação não encontrada.', 404);
        }

        if (! $solicitacao->status->isEditable()) {
            return ApiResponse::error('Não é possível anexar fotos nesta solicitação.', 409);
        }

        $file = $request->file('photo');
        $disk = (string) config('filesystems.object_disk', 's3');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = 'requests/'.$solicitacao->id;

        $path = $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        if ($path === false) {
            return ApiResponse::error('Falha ao enviar a foto para o Object Storage.', 500);
        }

        $ordem = (int) $solicitacao->fotos()->max('ordem') + 1;

        $foto = FotoSolicitacao::query()->create([
            'solicitacao_id' => $solicitacao->id,
            'url' => $path,
            'ordem' => $ordem,
        ]);

        ProcessSolicitacaoPhotoJob::dispatch($foto->id, $path);

        return ApiResponse::success((new FotoSolicitacaoResource($foto))->resolve($request), 202);
    }

    /**
     * @param  array<string, mixed>  $novoEscopo
     */
    private function escopoMudou(Solicitacao $solicitacao, array $novoEscopo): bool
    {
        return json_encode($solicitacao->escopo, JSON_THROW_ON_ERROR) !== json_encode($novoEscopo, JSON_THROW_ON_ERROR);
    }
}
