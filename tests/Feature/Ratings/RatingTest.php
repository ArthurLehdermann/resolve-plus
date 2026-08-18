<?php

namespace Tests\Feature\Ratings;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use App\Ratings\Avaliacao;
use App\Ratings\DirecaoAvaliacao;
use App\Requests\Solicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use App\Users\NivelConfianca;
use App\Users\PerfilProfissional;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_avalia_profissional_em_servico_aprovado(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto(StatusServico::Aprovado);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/rating", [
                'score' => 5,
                'comment' => 'Excelente serviço.',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.servico_id', $servico->id)
            ->assertJsonPath('data.autor_id', $cliente->id)
            ->assertJsonPath('data.alvo_id', $profissional->id)
            ->assertJsonPath('data.direcao', DirecaoAvaliacao::ClienteAvaliaProfissional->value)
            ->assertJsonPath('data.nota', 5)
            ->assertJsonPath('data.comentario', 'Excelente serviço.');

        $this->assertDatabaseHas('avaliacoes', [
            'servico_id' => $servico->id,
            'direcao' => DirecaoAvaliacao::ClienteAvaliaProfissional->value,
            'nota' => 5,
        ]);
    }

    public function test_profissional_avalia_cliente_em_servico_aprovado(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto(StatusServico::Aprovado);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/rating", [
                'score' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.direcao', DirecaoAvaliacao::ProfissionalAvaliaCliente->value)
            ->assertJsonPath('data.autor_id', $profissional->id)
            ->assertJsonPath('data.alvo_id', $cliente->id)
            ->assertJsonPath('data.nota', 4);
    }

    public function test_rn004_rejeita_avaliacao_quando_servico_nao_esta_aprovado(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/rating", [
                'score' => 5,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('avaliacoes', 0);
    }

    public function test_rn004_rejeita_avaliacao_em_agendado_em_andamento_e_cancelado(): void
    {
        foreach ([
            StatusServico::Agendado,
            StatusServico::EmAndamento,
            StatusServico::EmContestacao,
            StatusServico::Cancelado,
        ] as $status) {
            [$cliente, , $servico] = $this->contexto($status);

            $this->asUser($cliente)
                ->postJson("/api/v1/services/{$servico->id}/rating", [
                    'score' => 3,
                ])
                ->assertStatus(409);

            $this->assertSame(0, Avaliacao::query()->where('servico_id', $servico->id)->count());
        }
    }

    public function test_uma_avaliacao_por_direcao_no_mesmo_servico(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto(StatusServico::Aprovado);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 5])
            ->assertCreated();

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 4])
            ->assertStatus(409);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 3])
            ->assertCreated()
            ->assertJsonPath('data.direcao', DirecaoAvaliacao::ProfissionalAvaliaCliente->value);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 2])
            ->assertStatus(409);

        $this->assertSame(2, Avaliacao::query()->where('servico_id', $servico->id)->count());
    }

    public function test_unique_servico_id_direcao_e_garantido_no_banco(): void
    {
        $avaliacao = Avaliacao::factory()->create();

        $this->expectException(QueryException::class);

        Avaliacao::query()->create([
            'servico_id' => $avaliacao->servico_id,
            'autor_id' => $avaliacao->autor_id,
            'alvo_id' => $avaliacao->alvo_id,
            'direcao' => $avaliacao->direcao,
            'nota' => 1,
        ]);
    }

    public function test_recalcula_nota_media_do_profissional_em_decimos(): void
    {
        $profissional = $this->profissionalAtivo(now()->subDays(10));
        $primeiro = $this->servicoAprovadoPara($profissional);
        $segundo = $this->servicoAprovadoPara($profissional);

        $this->asUser($primeiro['cliente'])
            ->postJson("/api/v1/services/{$primeiro['servico']->id}/rating", ['score' => 5])
            ->assertCreated();

        $this->asUser($segundo['cliente'])
            ->postJson("/api/v1/services/{$segundo['servico']->id}/rating", ['score' => 4])
            ->assertCreated();

        $perfil = PerfilProfissional::query()->where('usuario_id', $profissional->id)->first();
        $this->assertNotNull($perfil);
        $this->assertSame(45, $perfil->nota_media_dez);
        $this->assertSame(2, $perfil->servicos_aprovados);
        $this->assertSame(NivelConfianca::Verificado, $perfil->nivel_confianca);
    }

    public function test_promove_para_bronze_quando_limiares_d4_sao_atingidos(): void
    {
        $profissional = $this->profissionalAtivo(now()->subDays(31));

        $servicos = [];
        for ($i = 0; $i < 3; $i++) {
            $servicos[] = $this->servicoAprovadoPara($profissional);
        }

        $this->asUser($servicos[0]['cliente'])
            ->postJson("/api/v1/services/{$servicos[0]['servico']->id}/rating", ['score' => 4])
            ->assertCreated();

        $perfil = PerfilProfissional::query()->where('usuario_id', $profissional->id)->first();
        $this->assertNotNull($perfil);
        $this->assertSame(40, $perfil->nota_media_dez);
        $this->assertSame(3, $perfil->servicos_aprovados);
        $this->assertSame(NivelConfianca::Bronze, $perfil->nivel_confianca);

        $this->asUser($profissional)
            ->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.trust_profile.nivel_confianca', 'BRONZE')
            ->assertJsonPath('data.trust_profile.servicos_aprovados', 3)
            ->assertJsonPath('data.trust_profile.nota_media', 4);
    }

    public function test_avaliacao_do_profissional_nao_altera_nota_media_do_perfil(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto(StatusServico::Aprovado);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 2])
            ->assertCreated();

        $this->assertNull(
            PerfilProfissional::query()->where('usuario_id', $profissional->id)->first(),
        );
        $this->assertNull(
            PerfilProfissional::query()->where('usuario_id', $cliente->id)->first(),
        );
    }

    public function test_terceiro_nao_pode_avaliar_e_nota_invalida_retorna_422(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::Aprovado);
        $estranho = Usuario::factory()->create();

        $this->asUser($estranho)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 5])
            ->assertForbidden();

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_rota_exige_autenticacao(): void
    {
        [, , $servico] = $this->contexto(StatusServico::Aprovado);

        $this->postJson("/api/v1/services/{$servico->id}/rating", ['score' => 5])
            ->assertUnauthorized();
    }

    /**
     * @return array{0: Usuario, 1: Usuario, 2: Servico}
     */
    private function contexto(StatusServico $status = StatusServico::Agendado): array
    {
        $cliente = Usuario::factory()->create();
        $profissional = $this->profissionalAtivo();
        $solicitacao = Solicitacao::factory()->contratada()->create([
            'cliente_id' => $cliente->id,
        ]);
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
        ]);

        $servico = Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => $status,
        ]);

        return [$cliente, $profissional, $servico];
    }

    /**
     * @return array{cliente: Usuario, servico: Servico}
     */
    private function servicoAprovadoPara(Usuario $profissional): array
    {
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->contratada()->create([
            'cliente_id' => $cliente->id,
        ]);
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
        ]);
        $servico = Servico::factory()->aprovado()->create([
            'proposta_id' => $proposta->id,
        ]);

        return ['cliente' => $cliente, 'servico' => $servico];
    }

    private function profissionalAtivo(?DateTimeInterface $criadoEm = null): Usuario
    {
        return Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
            'created_at' => $criadoEm ?? now(),
        ]);
    }

    private function asUser(Usuario $usuario): static
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->actingAs($usuario, 'sanctum');
    }
}
