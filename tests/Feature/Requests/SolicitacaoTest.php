<?php

namespace Tests\Feature\Requests;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\PropertyHistory\Property;
use App\PropertyHistory\PropertyOwnership;
use App\Proposals\Proposta;
use App\Requests\Events\SolicitacaoCriada;
use App\Requests\Jobs\ProcessSolicitacaoPhotoJob;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use App\Requests\TabelaPreco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_requests_creates_solicitacao_for_current_owner(): void
    {
        Event::fake([SolicitacaoCriada::class]);

        [$usuario, $token] = $this->clienteAutenticado();
        $property = Property::factory()->ownedBy($usuario)->create();
        $categoria = Categoria::factory()->mvp('pintura')->create();
        TabelaPreco::factory()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
            'valor_min' => 30000,
            'valor_max' => 150000,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'property_id' => $property->id,
                'category_id' => $categoria->id,
                'description' => 'Pintura da sala e corredor',
                'scope' => $this->escopoPintura(),
                'desired_date' => now()->addDays(5)->toDateString(),
                'estimated_price_min' => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', StatusSolicitacao::Aberta->value)
            ->assertJsonPath('data.property_id', $property->id)
            ->assertJsonPath('data.category_id', $categoria->id)
            ->assertJsonPath('data.scope.comodos', 2)
            ->assertJsonPath('data.estimated_price_min', 30000)
            ->assertJsonPath('data.estimated_price_max', 150000)
            ->assertJsonPath('data.estimated_price_factor_bp', 10000);

        $this->assertDatabaseHas('solicitacoes', [
            'cliente_id' => $usuario->id,
            'property_id' => $property->id,
            'categoria_id' => $categoria->id,
            'status' => StatusSolicitacao::Aberta->value,
            'faixa_preco_min' => 30000,
            'faixa_preco_max' => 150000,
        ]);

        Event::assertDispatched(SolicitacaoCriada::class);
    }

    public function test_post_requests_fails_when_no_active_price_table(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $property = Property::factory()->ownedBy($usuario)->create();
        $categoria = Categoria::factory()->mvp('pintura')->create();

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'property_id' => $property->id,
                'category_id' => $categoria->id,
                'description' => 'Sem tabela de preço cadastrada',
                'scope' => $this->escopoPintura(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PRECO_TABELA_AUSENTE');

        $this->assertDatabaseCount('solicitacoes', 0);
    }

    public function test_post_requests_estimate_previews_price_without_persisting(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $property = Property::factory()->ownedBy($usuario)->create();
        $categoria = Categoria::factory()->mvp('pintura')->create();
        TabelaPreco::factory()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
            'valor_min' => 30000,
            'valor_max' => 150000,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/requests/estimate', [
                'property_id' => $property->id,
                'category_id' => $categoria->id,
                'description' => 'Só quero ver a faixa antes de contratar',
                'scope' => $this->escopoPintura(),
            ])
            ->assertOk()
            ->assertJsonPath('data.estimated_price_min', 30000)
            ->assertJsonPath('data.estimated_price_max', 150000);

        $this->assertDatabaseCount('solicitacoes', 0);
    }

    public function test_post_requests_rejects_property_not_owned_by_authenticated_client(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $outro = Usuario::factory()->create();
        $property = Property::factory()->ownedBy($outro)->create();
        $categoria = Categoria::factory()->mvp('pintura')->create();

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'property_id' => $property->id,
                'category_id' => $categoria->id,
                'description' => 'Tentativa em imóvel alheio',
                'scope' => $this->escopoPintura(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['property_id']);

        $this->assertDatabaseCount('solicitacoes', 0);
        $this->assertTrue($usuario->exists);
    }

    public function test_post_requests_rejects_former_owner_after_ownership_ended(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $novoDono = Usuario::factory()->create();
        $property = Property::factory()->create();

        PropertyOwnership::query()->create([
            'property_id' => $property->id,
            'cliente_id' => $usuario->id,
            'desde' => now()->subMonth(),
            'ate' => now()->subDay(),
        ]);
        PropertyOwnership::query()->create([
            'property_id' => $property->id,
            'cliente_id' => $novoDono->id,
            'desde' => now()->subDay(),
            'ate' => null,
        ]);

        $categoria = Categoria::factory()->mvp('eletrica')->create();

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'property_id' => $property->id,
                'category_id' => $categoria->id,
                'description' => 'Ex-dono tentando abrir solicitação',
                'scope' => [
                    'tipo_servico' => 'INSTALACAO_PONTO',
                    'quantidade_pontos' => 1,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['property_id']);
    }

    public function test_post_requests_rejects_missing_required_scope_field(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $property = Property::factory()->ownedBy($usuario)->create();
        $categoria = Categoria::factory()->mvp('pintura')->create();

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'property_id' => $property->id,
                'category_id' => $categoria->id,
                'description' => 'Escopo incompleto',
                'scope' => [
                    'comodos' => 2,
                    'area_m2' => 35.5,
                    'tipo_tinta' => 'LATEX_PVA',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope.paredes_ou_teto']);

        $this->assertDatabaseCount('solicitacoes', 0);
    }

    public function test_get_requests_lists_only_authenticated_client_items(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $outro = Usuario::factory()->create();
        $categoria = Categoria::factory()->mvp('pintura')->create();

        $propria = Solicitacao::factory()->forCliente($usuario)->create([
            'categoria_id' => $categoria->id,
            'escopo' => $this->escopoPintura(),
        ]);
        Solicitacao::factory()->forCliente($outro)->create([
            'categoria_id' => $categoria->id,
            'escopo' => $this->escopoPintura(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $propria->id);
    }

    public function test_put_requests_updates_description_while_open(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $solicitacao = $this->solicitacaoDoCliente($usuario);

        $this->withToken($token)
            ->putJson('/api/v1/requests/'.$solicitacao->id, [
                'description' => 'Descrição atualizada',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Descrição atualizada');
    }

    public function test_put_requests_blocks_scope_change_after_proposal(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $solicitacao = $this->solicitacaoDoCliente($usuario);

        Proposta::factory()->create([
            'solicitacao_id' => $solicitacao->id,
        ]);

        $this->withToken($token)
            ->putJson('/api/v1/requests/'.$solicitacao->id, [
                'scope' => [
                    'comodos' => 4,
                    'area_m2' => 80.0,
                    'tipo_tinta' => 'ACRILICA',
                    'paredes_ou_teto' => 'PAREDES',
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $solicitacao->refresh();
        $this->assertSame(2, $solicitacao->escopo['comodos']);
    }

    public function test_put_requests_allows_scope_change_before_proposal(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $solicitacao = $this->solicitacaoDoCliente($usuario);

        $this->withToken($token)
            ->putJson('/api/v1/requests/'.$solicitacao->id, [
                'scope' => [
                    'comodos' => 3,
                    'area_m2' => 40.0,
                    'tipo_tinta' => 'ACRILICA',
                    'paredes_ou_teto' => 'TETO',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.scope.comodos', 3);
    }

    public function test_delete_requests_cancels_without_penalty(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $solicitacao = $this->solicitacaoDoCliente($usuario);

        $this->withToken($token)
            ->deleteJson('/api/v1/requests/'.$solicitacao->id)
            ->assertOk()
            ->assertJsonPath('data.status', StatusSolicitacao::Cancelada->value);

        $this->assertSame(StatusSolicitacao::Cancelada, $solicitacao->refresh()->status);
    }

    public function test_delete_requests_rejects_already_cancelled(): void
    {
        [$usuario, $token] = $this->clienteAutenticado();
        $solicitacao = $this->solicitacaoDoCliente($usuario, [
            'status' => StatusSolicitacao::Cancelada,
        ]);

        $this->withToken($token)
            ->deleteJson('/api/v1/requests/'.$solicitacao->id)
            ->assertStatus(409);
    }

    public function test_post_photos_uploads_and_dispatches_async_job(): void
    {
        Storage::fake((string) config('filesystems.object_disk', 's3'));
        Queue::fake();

        [$usuario, $token] = $this->clienteAutenticado();
        $solicitacao = $this->solicitacaoDoCliente($usuario);
        $photo = UploadedFile::fake()->image('parede.jpg', 800, 600);

        $response = $this->withToken($token)
            ->postJson('/api/v1/requests/'.$solicitacao->id.'/photos', [
                'photo' => $photo,
            ]);

        $response->assertAccepted()
            ->assertJsonPath('success', true);

        $fotoUrl = $response->json('data.url');
        $this->assertIsString($fotoUrl);
        $this->assertStringStartsWith('requests/'.$solicitacao->id.'/', $fotoUrl);

        Storage::disk((string) config('filesystems.object_disk', 's3'))
            ->assertExists($fotoUrl);

        Queue::assertPushed(ProcessSolicitacaoPhotoJob::class, function (ProcessSolicitacaoPhotoJob $job) use ($fotoUrl): bool {
            return $job->originalPath === $fotoUrl;
        });
    }

    public function test_process_solicitacao_photo_job_stores_thumbnail(): void
    {
        $disk = (string) config('filesystems.object_disk', 's3');
        Storage::fake($disk);

        [$usuario] = $this->clienteAutenticado();
        $solicitacao = $this->solicitacaoDoCliente($usuario);
        $photo = UploadedFile::fake()->image('parede.png', 400, 300);
        $originalPath = $photo->storeAs('requests/'.$solicitacao->id, 'original.png', [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        $this->assertNotFalse($originalPath);

        $foto = $solicitacao->fotos()->create([
            'url' => $originalPath,
            'ordem' => 1,
        ]);

        (new ProcessSolicitacaoPhotoJob($foto->id, $originalPath))->handle();

        $foto->refresh();
        $expectedThumb = 'requests/'.$solicitacao->id.'/original_thumb.jpg';
        $this->assertSame($expectedThumb, $foto->url);
        Storage::disk($disk)->assertExists($expectedThumb);
    }

    public function test_requests_endpoints_require_bearer_token(): void
    {
        $this->getJson('/api/v1/requests')->assertUnauthorized();
        $this->postJson('/api/v1/requests', [])->assertUnauthorized();
    }

    public function test_profissional_cannot_create_request(): void
    {
        $usuario = Usuario::factory()->profissional()->create([
            'tipo' => TipoUsuario::Profissional,
        ]);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'property_id' => fake()->uuid(),
                'category_id' => fake()->uuid(),
                'description' => 'Profissional não cria solicitação',
                'scope' => [],
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Usuario, 1: string}
     */
    private function clienteAutenticado(): array
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('auth')->plainTextToken;

        return [$usuario, $token];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function solicitacaoDoCliente(Usuario $usuario, array $overrides = []): Solicitacao
    {
        $categoria = Categoria::factory()->mvp('pintura')->create();
        TabelaPreco::factory()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
            'valor_min' => 30000,
            'valor_max' => 150000,
        ]);

        return Solicitacao::factory()->forCliente($usuario)->create([
            'categoria_id' => $categoria->id,
            'escopo' => $this->escopoPintura(),
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function escopoPintura(): array
    {
        return [
            'comodos' => 2,
            'area_m2' => 35.5,
            'tipo_tinta' => 'LATEX_PVA',
            'paredes_ou_teto' => 'PAREDES_E_TETO',
        ];
    }
}
