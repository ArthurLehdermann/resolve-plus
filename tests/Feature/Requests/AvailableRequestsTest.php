<?php

namespace Tests\Feature\Requests;

use App\Auth\Enums\StatusConta;
use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\Requests\Solicitacao;
use App\Users\PerfilProfissional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailableRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profissional_pendente_verificacao_pode_ver_oportunidades(): void
    {
        $profissional = Usuario::factory()->profissional()->create([
            'status' => StatusConta::PendenteVerificacao,
        ]);
        PerfilProfissional::factory()->create([
            'usuario_id' => $profissional->id,
            'categorias_atendidas' => ['pintura'],
        ]);

        $categoria = Categoria::factory()->mvp('pintura')->create();
        $solicitacao = Solicitacao::factory()->create([
            'categoria_id' => $categoria->id,
        ]);

        $token = $profissional->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/requests/available');

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame([$solicitacao->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_filtra_por_categorias_atendidas_do_profissional(): void
    {
        $profissional = Usuario::factory()->profissional()->create([
            'status' => StatusConta::Ativa,
        ]);
        PerfilProfissional::factory()->create([
            'usuario_id' => $profissional->id,
            'categorias_atendidas' => ['pintura'],
        ]);

        $categoriaPintura = Categoria::factory()->mvp('pintura')->create();
        $categoriaEletrica = Categoria::factory()->mvp('eletrica')->create();

        $solicitacaoPintura = Solicitacao::factory()->create(['categoria_id' => $categoriaPintura->id]);
        Solicitacao::factory()->create(['categoria_id' => $categoriaEletrica->id]);

        $token = $profissional->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/requests/available');

        $response->assertOk();
        $this->assertSame([$solicitacaoPintura->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_nao_lista_solicitacao_contratada_ou_cancelada(): void
    {
        $profissional = Usuario::factory()->profissional()->create();
        PerfilProfissional::factory()->create([
            'usuario_id' => $profissional->id,
            'categorias_atendidas' => ['pintura'],
        ]);
        $categoria = Categoria::factory()->mvp('pintura')->create();

        Solicitacao::factory()->contratada()->create(['categoria_id' => $categoria->id]);
        Solicitacao::factory()->cancelada()->create(['categoria_id' => $categoria->id]);

        $token = $profissional->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/requests/available');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_retorna_vazio_quando_profissional_sem_categorias_atendidas(): void
    {
        $profissional = Usuario::factory()->profissional()->create();
        $categoria = Categoria::factory()->mvp('pintura')->create();
        Solicitacao::factory()->create(['categoria_id' => $categoria->id]);

        $token = $profissional->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/requests/available');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('pagination.total'));
    }

    public function test_cliente_nao_pode_acessar_oportunidades(): void
    {
        $cliente = Usuario::factory()->create();
        $token = $cliente->createToken('auth')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/requests/available')->assertForbidden();
    }
}
