<?php

namespace Tests\Feature\Notifications;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\Notifications\Notificacao;
use App\Notifications\TipoNotificacao;
use App\Proposals\Events\ProposalAccepted;
use App\Proposals\Events\ProposalCreated;
use App\Proposals\Proposta;
use App\Requests\Events\SolicitacaoCriada;
use App\Requests\Solicitacao;
use App\Services\Events\ServiceApproved;
use App\Services\Events\ServiceFinished;
use App\Services\Events\ServiceStarted;
use App\Services\Servico;
use App\Users\PerfilProfissional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_lista_apenas_as_proprias_notificacoes_mais_recentes_primeiro(): void
    {
        $usuario = Usuario::factory()->create();
        $outro = Usuario::factory()->create();

        $antiga = Notificacao::factory()->create([
            'usuario_id' => $usuario->id,
            'titulo' => 'Mais antiga',
            'criado_em' => now()->subDay(),
        ]);
        $recente = Notificacao::factory()->create([
            'usuario_id' => $usuario->id,
            'titulo' => 'Mais recente',
            'criado_em' => now(),
        ]);
        Notificacao::factory()->create(['usuario_id' => $outro->id, 'titulo' => 'De outro usuário']);

        $this->asUser($usuario)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.1.id', $antiga->id)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonMissing(['titulo' => 'De outro usuário']);
    }

    public function test_feed_traz_a_contagem_de_nao_lidas_para_o_badge(): void
    {
        $usuario = Usuario::factory()->create();
        Notificacao::factory()->count(3)->create(['usuario_id' => $usuario->id]);
        Notificacao::factory()->lida()->create(['usuario_id' => $usuario->id]);

        $this->asUser($usuario)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 3)
            ->assertJsonPath('pagination.total', 4);
    }

    public function test_filtro_unread_devolve_so_as_pendentes(): void
    {
        $usuario = Usuario::factory()->create();
        Notificacao::factory()->count(2)->create(['usuario_id' => $usuario->id]);
        Notificacao::factory()->lida()->create(['usuario_id' => $usuario->id]);

        $this->asUser($usuario)
            ->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('unread_count', 2);
    }

    public function test_marcar_como_lida_carimba_a_data(): void
    {
        $usuario = Usuario::factory()->create();
        $notificacao = Notificacao::factory()->create(['usuario_id' => $usuario->id]);

        $this->asUser($usuario)
            ->putJson("/api/v1/notifications/{$notificacao->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notificacao->id);

        $this->assertNotNull($notificacao->refresh()->lida_em);
    }

    public function test_marcar_lida_de_novo_nao_move_a_data_da_primeira_leitura(): void
    {
        $usuario = Usuario::factory()->create();
        $primeiraLeitura = now()->subHours(3);
        $notificacao = Notificacao::factory()->create([
            'usuario_id' => $usuario->id,
            'lida_em' => $primeiraLeitura,
        ]);

        $this->asUser($usuario)
            ->putJson("/api/v1/notifications/{$notificacao->id}/read")
            ->assertOk();

        $this->assertSame(
            $primeiraLeitura->utc()->toIso8601String(),
            $notificacao->refresh()->lida_em->utc()->toIso8601String(),
        );
    }

    public function test_nao_marca_como_lida_notificacao_de_outro_usuario(): void
    {
        $usuario = Usuario::factory()->create();
        $notificacao = Notificacao::factory()->create(['usuario_id' => Usuario::factory()->create()->id]);

        $this->asUser($usuario)
            ->putJson("/api/v1/notifications/{$notificacao->id}/read")
            ->assertNotFound();

        $this->assertNull($notificacao->refresh()->lida_em);
    }

    public function test_marcar_todas_zera_o_badge(): void
    {
        $usuario = Usuario::factory()->create();
        Notificacao::factory()->count(3)->create(['usuario_id' => $usuario->id]);
        $deOutro = Notificacao::factory()->create(['usuario_id' => Usuario::factory()->create()->id]);

        $this->asUser($usuario)
            ->putJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marcadas', 3);

        $this->asUser($usuario)
            ->getJson('/api/v1/notifications')
            ->assertJsonPath('unread_count', 0);

        $this->assertNull($deOutro->refresh()->lida_em);
    }

    public function test_feed_exige_autenticacao(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_solicitacao_nova_notifica_profissional_que_atende_a_categoria(): void
    {
        $categoria = Categoria::factory()->create(['codigo' => 'eletrica']);
        $outraCategoria = Categoria::factory()->create(['codigo' => 'hidraulica']);

        $atende = $this->profissionalQueAtende(['eletrica']);
        $naoAtende = $this->profissionalQueAtende(['hidraulica']);
        $semPerfil = $this->profissionalAtivo();

        $solicitacao = Solicitacao::factory()->create(['categoria_id' => $categoria->id]);
        event(new SolicitacaoCriada($solicitacao));

        $this->assertDatabaseHas('notificacoes', [
            'usuario_id' => $atende->id,
            'tipo' => TipoNotificacao::SolicitacaoNova->value,
        ]);
        $this->assertDatabaseMissing('notificacoes', ['usuario_id' => $naoAtende->id]);
        $this->assertDatabaseMissing('notificacoes', ['usuario_id' => $semPerfil->id]);

        $notificacao = Notificacao::query()->where('usuario_id', $atende->id)->firstOrFail();
        $this->assertSame($solicitacao->id, $notificacao->dados['solicitacao_id']);
        $this->assertNotSame($outraCategoria->id, $solicitacao->categoria_id);
    }

    public function test_profissional_inativo_nao_recebe_solicitacao_nova(): void
    {
        $categoria = Categoria::factory()->create(['codigo' => 'eletrica']);
        $suspenso = $this->profissionalQueAtende(['eletrica'], StatusConta::Suspensa);

        event(new SolicitacaoCriada(Solicitacao::factory()->create(['categoria_id' => $categoria->id])));

        $this->assertDatabaseMissing('notificacoes', ['usuario_id' => $suspenso->id]);
    }

    public function test_proposta_criada_notifica_o_cliente_dono_da_solicitacao(): void
    {
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->create(['cliente_id' => $cliente->id]);
        $proposta = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);

        event(new ProposalCreated($proposta));

        $notificacao = Notificacao::query()->where('usuario_id', $cliente->id)->firstOrFail();
        $this->assertSame(TipoNotificacao::PropostaRecebida, $notificacao->tipo);
        $this->assertSame($solicitacao->id, $notificacao->dados['solicitacao_id']);
    }

    public function test_proposta_aceita_notifica_o_profissional(): void
    {
        [, $profissional, $servico] = $this->contexto();
        $proposta = Proposta::query()->findOrFail($servico->proposta_id);

        event(new ProposalAccepted($proposta, $servico));

        $notificacao = Notificacao::query()->where('usuario_id', $profissional->id)->firstOrFail();
        $this->assertSame(TipoNotificacao::PropostaAceita, $notificacao->tipo);
        $this->assertSame($servico->id, $notificacao->dados['servico_id']);
    }

    public function test_inicio_e_conclusao_notificam_o_cliente_e_aprovacao_notifica_o_profissional(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();

        event(new ServiceStarted($servico));
        event(new ServiceFinished($servico));
        event(new ServiceApproved($servico));

        $doCliente = Notificacao::query()->where('usuario_id', $cliente->id)->pluck('tipo');
        $this->assertEqualsCanonicalizing(
            [TipoNotificacao::ServicoIniciado, TipoNotificacao::ServicoConcluido],
            $doCliente->all(),
        );

        $doProfissional = Notificacao::query()->where('usuario_id', $profissional->id)->firstOrFail();
        $this->assertSame(TipoNotificacao::ServicoAprovado, $doProfissional->tipo);
    }

    public function test_aprovacao_automatica_explica_que_o_prazo_venceu(): void
    {
        [, $profissional, $servico] = $this->contexto();

        event(new ServiceApproved($servico, automatico: true));

        $notificacao = Notificacao::query()->where('usuario_id', $profissional->id)->firstOrFail();
        $this->assertStringContainsString('automaticamente', $notificacao->corpo);
    }

    /**
     * @param  list<string>  $categorias
     */
    private function profissionalQueAtende(array $categorias, StatusConta $status = StatusConta::Ativa): Usuario
    {
        $profissional = Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => $status,
        ]);

        PerfilProfissional::factory()->create([
            'usuario_id' => $profissional->id,
            'categorias_atendidas' => $categorias,
        ]);

        return $profissional;
    }

    private function profissionalAtivo(): Usuario
    {
        return Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
        ]);
    }

    /**
     * @return array{0: Usuario, 1: Usuario, 2: Servico}
     */
    private function contexto(): array
    {
        $cliente = Usuario::factory()->create();
        $profissional = $this->profissionalAtivo();
        $solicitacao = Solicitacao::factory()->contratada()->create(['cliente_id' => $cliente->id]);
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
        ]);
        $servico = Servico::factory()->create(['proposta_id' => $proposta->id]);

        return [$cliente, $profissional, $servico];
    }

    private function asUser(Usuario $usuario): static
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->actingAs($usuario, 'sanctum');
    }
}
