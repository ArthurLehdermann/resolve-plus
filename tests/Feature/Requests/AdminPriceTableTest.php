<?php

namespace Tests\Feature\Requests;

use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\Requests\TabelaPreco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPriceTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_price_table(): void
    {
        $admin = Usuario::factory()->admin()->create();
        $token = $admin->createToken('auth')->plainTextToken;
        $categoria = Categoria::factory()->mvp('eletrica')->create();

        $create = $this->withToken($token)
            ->postJson('/api/v1/admin/price-tables', [
                'categoria_id' => $categoria->id,
                'cidade' => 'São Paulo',
                'valor_min' => 8000,
                'valor_max' => 25000,
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cidade', 'São Paulo')
            ->assertJsonPath('data.valor_min', 8000);

        $id = $create->json('data.id');

        $this->withToken($token)
            ->putJson('/api/v1/admin/price-tables/'.$id, [
                'valor_min' => 9000,
            ])
            ->assertOk()
            ->assertJsonPath('data.valor_min', 9000)
            ->assertJsonPath('data.valor_max', 25000);

        $this->assertDatabaseHas('tabelas_preco', [
            'id' => $id,
            'valor_min' => 9000,
        ]);
    }

    public function test_creating_second_active_row_for_same_categoria_cidade_conflicts(): void
    {
        $admin = Usuario::factory()->admin()->create();
        $token = $admin->createToken('auth')->plainTextToken;
        $categoria = Categoria::factory()->mvp('eletrica')->create();
        TabelaPreco::factory()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/admin/price-tables', [
                'categoria_id' => $categoria->id,
                'cidade' => 'São Paulo',
                'valor_min' => 5000,
                'valor_max' => 10000,
            ])
            ->assertStatus(409);
    }

    public function test_update_rejects_valor_max_below_valor_min(): void
    {
        $admin = Usuario::factory()->admin()->create();
        $token = $admin->createToken('auth')->plainTextToken;
        $tabela = TabelaPreco::factory()->create();

        $this->withToken($token)
            ->putJson('/api/v1/admin/price-tables/'.$tabela->id, [
                'valor_max' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['valor_max']);
    }

    public function test_non_admin_cannot_access_price_tables(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('auth')->plainTextToken;
        $tabela = TabelaPreco::factory()->create();

        $this->withToken($token)->getJson('/api/v1/admin/price-tables')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/admin/price-tables', [])->assertForbidden();
        $this->withToken($token)
            ->putJson('/api/v1/admin/price-tables/'.$tabela->id, ['valor_min' => 1])
            ->assertForbidden();
    }
}
