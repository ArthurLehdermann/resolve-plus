<?php

namespace Tests\Feature\Categories;

use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private static function templateValido(): array
    {
        return [
            'tipo_servico' => [
                'tipo' => 'enum',
                'obrigatorio' => true,
                'rotulo' => 'Tipo de serviço',
                'valores' => ['DIAGNOSTICO'],
            ],
        ];
    }

    public function test_public_list_returns_active_categories_without_auth(): void
    {
        $this->seed(CategoriaSeeder::class);
        Categoria::factory()->inativa()->create(['codigo' => 'arquivada']);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $codigos = collect($response->json('data'))->pluck('codigo')->all();
        $this->assertEqualsCanonicalizing(
            ['eletrica', 'hidraulica', 'pintura', 'pequenos_reparos', 'montagem'],
            $codigos
        );
        $this->assertNotContains('arquivada', $codigos);
    }

    public function test_public_show_includes_full_template_escopo_without_auth(): void
    {
        $this->seed(CategoriaSeeder::class);
        $eletrica = Categoria::query()->where('codigo', 'eletrica')->firstOrFail();

        $response = $this->getJson('/api/v1/categories/'.$eletrica->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo', 'eletrica')
            ->assertJsonPath('data.nome', 'Elétrica')
            ->assertJsonPath('data.template_escopo.tipo_servico.tipo', 'enum')
            ->assertJsonPath('data.template_escopo.quantidade_pontos.min', 1)
            ->assertJsonPath('data.template_escopo.tipo_servico.valores.0', 'INSTALACAO_PONTO');
    }

    public function test_public_show_returns_404_for_inactive_or_missing_category(): void
    {
        $inativa = Categoria::factory()->inativa()->create();

        $this->getJson('/api/v1/categories/'.$inativa->id)->assertNotFound();
        $this->getJson('/api/v1/categories/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_admin_can_create_update_and_delete_category(): void
    {
        $admin = Usuario::factory()->admin()->create();
        $token = $admin->createToken('auth')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson('/api/v1/admin/categories', [
                'codigo' => 'ar_condicionado',
                'nome' => 'Ar-condicionado',
                'descricao' => 'Instalação e manutenção de ar-condicionado.',
                'ativo' => true,
                'template_escopo' => self::templateValido(),
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo', 'ar_condicionado')
            ->assertJsonPath('data.nome', 'Ar-condicionado');

        $id = $create->json('data.id');
        $this->assertNotNull(Categoria::query()->find($id));

        $this->withToken($token)
            ->putJson('/api/v1/admin/categories/'.$id, [
                'nome' => 'Climatização',
                'ativo' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Climatização')
            ->assertJsonPath('data.ativo', false);

        $this->withToken($token)
            ->getJson('/api/v1/admin/categories/'.$id)
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/categories/'.$id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(Categoria::query()->find($id));
    }

    public function test_admin_list_includes_inactive_categories(): void
    {
        $this->seed(CategoriaSeeder::class);
        Categoria::factory()->inativa()->create(['codigo' => 'arquivada']);
        $admin = Usuario::factory()->admin()->create();
        $token = $admin->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/categories');

        $response->assertOk();
        $codigos = collect($response->json('data'))->pluck('codigo')->all();
        $this->assertContains('arquivada', $codigos);
        $this->assertContains('eletrica', $codigos);
    }

    public function test_unauthenticated_user_cannot_access_admin_crud(): void
    {
        $categoria = Categoria::factory()->create();

        $this->getJson('/api/v1/admin/categories')->assertUnauthorized();
        $this->getJson('/api/v1/admin/categories/'.$categoria->id)->assertUnauthorized();
        $this->postJson('/api/v1/admin/categories', [
            'codigo' => 'nova',
            'nome' => 'Nova',
            'template_escopo' => self::templateValido(),
        ])->assertUnauthorized();
        $this->putJson('/api/v1/admin/categories/'.$categoria->id, [
            'nome' => 'Editada',
        ])->assertUnauthorized();
        $this->deleteJson('/api/v1/admin/categories/'.$categoria->id)->assertUnauthorized();
    }

    public function test_non_admin_cannot_create_or_edit_categories(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Original']);
        $payload = [
            'codigo' => 'nova_cat',
            'nome' => 'Nova categoria',
            'template_escopo' => self::templateValido(),
        ];

        foreach ([Usuario::factory()->create(), Usuario::factory()->profissional()->create()] as $usuario) {
            $token = $usuario->createToken('auth')->plainTextToken;

            $this->withToken($token)
                ->postJson('/api/v1/admin/categories', $payload)
                ->assertForbidden();

            $this->withToken($token)
                ->putJson('/api/v1/admin/categories/'.$categoria->id, ['nome' => 'Hack'])
                ->assertForbidden();

            $this->withToken($token)
                ->deleteJson('/api/v1/admin/categories/'.$categoria->id)
                ->assertForbidden();

            $this->withToken($token)
                ->getJson('/api/v1/admin/categories')
                ->assertForbidden();

            $this->withToken($token)
                ->getJson('/api/v1/admin/categories/'.$categoria->id)
                ->assertForbidden();
        }

        $this->assertSame('Original', $categoria->fresh()->nome);
        $this->assertNull(Categoria::query()->where('codigo', 'nova_cat')->first());
    }

    public function test_seeder_persists_five_mvp_categories_from_d3_fixture(): void
    {
        $this->seed(CategoriaSeeder::class);

        $this->assertSame(5, Categoria::query()->count());
        $this->assertEqualsCanonicalizing(
            ['eletrica', 'hidraulica', 'pintura', 'pequenos_reparos', 'montagem'],
            Categoria::query()->pluck('codigo')->all()
        );

        $pintura = Categoria::query()->where('codigo', 'pintura')->firstOrFail();
        $this->assertTrue($pintura->ativo);
        $this->assertSame('Pintura', $pintura->nome);
        $this->assertSame('number', $pintura->template_escopo['area_m2']['tipo']);
        $this->assertContains('LATEX_PVA', $pintura->template_escopo['tipo_tinta']['valores']);
    }
}
