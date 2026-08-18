<?php

namespace Tests\Feature\Proposals;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Proposals\Events\ProposalAccepted;
use App\Proposals\Events\ProposalCreated;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_unique_index_exists_on_propostas(): void
    {
        $this->assertTrue(Schema::hasTable('propostas'));
        $this->assertFalse(Schema::hasColumn('propostas', 'escopo'));
        $this->assertFalse(Schema::hasColumn('propostas', 'scope'));

        $sql = (string) (DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'propostas_solicitacao_aceita_unique'",
        )->sql ?? '');

        $this->assertNotSame('', $sql);
        $this->assertStringContainsString('solicitacao_id', $sql);
        $this->assertStringContainsString("status = 'ACEITA'", $sql);
    }

    public function test_profissional_ativo_envia_proposta(): void
    {
        Event::fake([ProposalCreated::class]);

        $solicitacao = Solicitacao::factory()->create();
        $profissional = $this->profissionalAtivo();
        $token = $this->token($profissional);

        $response = $this->withToken($token)
            ->postJson("/api/v1/requests/{$solicitacao->id}/proposals", [
                'price' => 35000,
                'deadline_days' => 2,
                'warranty_days' => 90,
                'notes' => 'Posso iniciar na segunda.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price', 35000)
            ->assertJsonPath('data.deadline_days', 2)
            ->assertJsonPath('data.warranty_days', 90)
            ->assertJsonPath('data.notes', 'Posso iniciar na segunda.')
            ->assertJsonPath('data.status', 'ENVIADA')
            ->assertJsonPath('data.professional.id', $profissional->id)
            ->assertJsonMissingPath('data.scope')
            ->assertJsonMissingPath('data.escopo');

        $solicitacao->refresh();
        $this->assertSame(StatusSolicitacao::RecebendoPropostas, $solicitacao->status);
        $this->assertSame(1, Proposta::query()->count());
        Event::assertDispatched(ProposalCreated::class);
    }

    public function test_inv_002_profissional_inativo_nao_envia_proposta(): void
    {
        $solicitacao = Solicitacao::factory()->create();
        $pendente = Usuario::factory()->profissional()->create();
        $cliente = Usuario::factory()->create();

        $this->withToken($this->token($pendente))
            ->postJson("/api/v1/requests/{$solicitacao->id}/proposals", $this->payload())
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->withToken($this->token($cliente))
            ->postJson("/api/v1/requests/{$solicitacao->id}/proposals", $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Proposta::query()->count());
    }

    public function test_lista_propostas_inclui_trust_level_do_profissional(): void
    {
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->recebendoPropostas()->create([
            'cliente_id' => $cliente->id,
        ]);
        $profissional = $this->profissionalAtivo();
        Proposta::factory()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
            'valor' => 12000,
        ]);

        $this->withToken($this->token($cliente))
            ->getJson("/api/v1/requests/{$solicitacao->id}/proposals")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.price', 12000)
            ->assertJsonPath('data.0.professional.id', $profissional->id)
            ->assertJsonPath('data.0.professional.trust_level', null)
            ->assertJsonPath('data.0.professional.average_rating', null)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_accept_cria_servico_e_recusa_demais_inv_011(): void
    {
        Event::fake([ProposalAccepted::class]);

        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->recebendoPropostas()->create([
            'cliente_id' => $cliente->id,
        ]);
        $vencedora = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);
        $outra = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);
        $terceira = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);

        $this->withToken($this->token($cliente))
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/proposals/{$vencedora->id}/accept")
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACEITA')
            ->assertJsonPath('data.service.status', 'AGENDADO')
            ->assertJsonPath('data.service.proposal_id', $vencedora->id);

        $vencedora->refresh();
        $outra->refresh();
        $terceira->refresh();
        $solicitacao->refresh();

        $this->assertSame(StatusProposta::Aceita, $vencedora->status);
        $this->assertSame(StatusProposta::Recusada, $outra->status);
        $this->assertSame(StatusProposta::Recusada, $terceira->status);
        $this->assertSame(StatusSolicitacao::Contratada, $solicitacao->status);
        $this->assertSame(1, Servico::query()->count());
        $this->assertSame(StatusServico::Agendado, Servico::query()->firstOrFail()->status);
        $this->assertSame(1, Proposta::query()->where('status', StatusProposta::Aceita)->count());
        Event::assertDispatched(ProposalAccepted::class);
    }

    public function test_inv_010_duas_aceitacoes_simultaneas_so_uma_vence(): void
    {
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->recebendoPropostas()->create([
            'cliente_id' => $cliente->id,
        ]);
        $primeira = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);
        $segunda = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);
        $token = $this->token($cliente);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/proposals/{$primeira->id}/accept")
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/proposals/{$segunda->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(1, Proposta::query()->where('status', StatusProposta::Aceita)->count());
        $this->assertSame(StatusProposta::Aceita, $primeira->fresh()->status);
        $this->assertSame(StatusProposta::Recusada, $segunda->fresh()->status);
        $this->assertSame(1, Servico::query()->count());
    }

    public function test_inv_010_indice_parcial_rejeita_segunda_aceita_na_mesma_solicitacao(): void
    {
        $solicitacao = Solicitacao::factory()->recebendoPropostas()->create();
        $primeira = Proposta::factory()->create([
            'solicitacao_id' => $solicitacao->id,
            'status' => StatusProposta::Aceita,
        ]);
        $segunda = Proposta::factory()->create([
            'solicitacao_id' => $solicitacao->id,
        ]);

        try {
            $segunda->update(['status' => StatusProposta::Aceita]);
            $this->fail('O índice parcial UNIQUE(solicitacao_id) WHERE status=ACEITA deveria impedir a segunda aceitação.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, Proposta::query()->where('status', StatusProposta::Aceita)->count());
        $this->assertSame($primeira->id, Proposta::query()->where('status', StatusProposta::Aceita)->value('id'));
    }

    public function test_inv_010_corrida_contra_indice_parcial_vira_409(): void
    {
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->recebendoPropostas()->create([
            'cliente_id' => $cliente->id,
        ]);
        Proposta::factory()->create([
            'solicitacao_id' => $solicitacao->id,
            'status' => StatusProposta::Aceita,
        ]);
        $concorrente = Proposta::factory()->create([
            'solicitacao_id' => $solicitacao->id,
        ]);

        $this->withToken($this->token($cliente))
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/proposals/{$concorrente->id}/accept")
            ->assertStatus(409);

        $this->assertSame(1, Proposta::query()->where('status', StatusProposta::Aceita)->count());
        $this->assertSame(0, Servico::query()->count());
    }

    public function test_inv_012_bloqueia_aceite_em_solicitacao_cancelada_ou_expirada(): void
    {
        $cliente = Usuario::factory()->create();

        foreach ([StatusSolicitacao::Cancelada, StatusSolicitacao::Expirada] as $status) {
            $solicitacao = Solicitacao::factory()->create([
                'cliente_id' => $cliente->id,
                'status' => $status,
            ]);
            $proposta = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);

            $this->withToken($this->token($cliente))
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson("/api/v1/proposals/{$proposta->id}/accept")
                ->assertStatus(409)
                ->assertJsonPath('success', false);

            $this->assertSame(StatusProposta::Enviada, $proposta->fresh()->status);
            $this->assertSame(0, Servico::query()->count());
        }
    }

    public function test_inv_013_outro_cliente_nao_aceita_proposta(): void
    {
        $dono = Usuario::factory()->create();
        $intruso = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->recebendoPropostas()->create([
            'cliente_id' => $dono->id,
        ]);
        $proposta = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);

        $this->withToken($this->token($intruso))
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/proposals/{$proposta->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertSame(StatusProposta::Enviada, $proposta->fresh()->status);
        $this->assertSame(0, Servico::query()->count());
    }

    public function test_profissional_retira_proposta_antes_do_aceite(): void
    {
        $profissional = $this->profissionalAtivo();
        $proposta = Proposta::factory()->create([
            'profissional_id' => $profissional->id,
        ]);

        $this->withToken($this->token($profissional))
            ->postJson("/api/v1/proposals/{$proposta->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', 'RETIRADA');

        $this->assertSame(StatusProposta::Retirada, $proposta->fresh()->status);
    }

    public function test_nao_retira_proposta_de_outro_profissional(): void
    {
        $autor = $this->profissionalAtivo();
        $outro = $this->profissionalAtivo();
        $proposta = Proposta::factory()->create(['profissional_id' => $autor->id]);

        $this->withToken($this->token($outro))
            ->postJson("/api/v1/proposals/{$proposta->id}/withdraw")
            ->assertForbidden();
    }

    public function test_nao_retira_proposta_ja_aceita(): void
    {
        $autor = $this->profissionalAtivo();
        $proposta = Proposta::factory()->create([
            'profissional_id' => $autor->id,
            'status' => StatusProposta::Aceita,
        ]);

        $this->withToken($this->token($autor))
            ->postJson("/api/v1/proposals/{$proposta->id}/withdraw")
            ->assertStatus(409);
    }

    public function test_accept_exige_idempotency_key(): void
    {
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->recebendoPropostas()->create([
            'cliente_id' => $cliente->id,
        ]);
        $proposta = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);

        $this->withToken($this->token($cliente))
            ->postJson("/api/v1/proposals/{$proposta->id}/accept")
            ->assertStatus(422);
    }

    public function test_rotas_de_proposta_exigem_autenticacao(): void
    {
        $solicitacao = Solicitacao::factory()->create();
        $proposta = Proposta::factory()->create(['solicitacao_id' => $solicitacao->id]);

        $this->postJson("/api/v1/requests/{$solicitacao->id}/proposals", $this->payload())
            ->assertUnauthorized();
        $this->getJson("/api/v1/requests/{$solicitacao->id}/proposals")
            ->assertUnauthorized();
        $this->postJson("/api/v1/proposals/{$proposta->id}/accept")
            ->assertUnauthorized();
        $this->postJson("/api/v1/proposals/{$proposta->id}/withdraw")
            ->assertUnauthorized();
    }

    /**
     * @return array<string, int>
     */
    private function payload(): array
    {
        return [
            'price' => 10000,
            'deadline_days' => 3,
            'warranty_days' => 60,
        ];
    }

    private function profissionalAtivo(): Usuario
    {
        return Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
        ]);
    }

    private function token(Usuario $usuario): string
    {
        return $usuario->createToken('auth')->plainTextToken;
    }
}
