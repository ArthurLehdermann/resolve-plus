<?php

namespace Tests\Feature\Services;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;
use App\Requests\Solicitacao;
use App\Services\Agenda;
use App\Services\Events\ServiceStarted;
use App\Services\Mensagem;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServiceExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_servico_nasce_agendado(): void
    {
        $servico = Servico::factory()->create();

        $this->assertSame(StatusServico::Agendado, $servico->status);
        $this->assertNull($servico->inicio);
        $this->assertSame(StatusProposta::Aceita, $servico->proposta->status);
    }

    public function test_profissional_inicia_servico_agendado(): void
    {
        Event::fake([ServiceStarted::class]);

        [$cliente, $profissional, $servico] = $this->contexto();

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/start")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $servico->id)
            ->assertJsonPath('data.status', 'EM_ANDAMENTO')
            ->assertJsonPath('data.proposal_id', $servico->proposta_id);

        $servico->refresh();
        $this->assertSame(StatusServico::EmAndamento, $servico->status);
        $this->assertNotNull($servico->inicio);
        Event::assertDispatched(ServiceStarted::class);
        $this->assertSame($cliente->id, $servico->clienteId());
    }

    public function test_cliente_nao_pode_iniciar_servico(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/start")
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertSame(StatusServico::Agendado, $servico->fresh()->status);
        $this->assertSame($profissional->id, $servico->profissionalId());
    }

    public function test_profissional_alheio_nao_pode_iniciar_servico(): void
    {
        [, , $servico] = $this->contexto();
        $intruso = $this->profissionalAtivo();

        $this->asUser($intruso)
            ->postJson("/api/v1/services/{$servico->id}/start")
            ->assertForbidden();

        $this->assertSame(StatusServico::Agendado, $servico->fresh()->status);
    }

    public function test_nao_inicia_servico_fora_de_agendado(): void
    {
        [, $profissional, $servico] = $this->contexto(StatusServico::EmAndamento);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/start")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_cliente_e_profissional_agendam_e_reagendam(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();

        $created = $this->asUser($cliente)
            ->postJson('/api/v1/schedule', [
                'service_id' => $servico->id,
                'date' => '2026-09-01',
                'time' => '14:00',
                'notes' => 'Portão dos fundos',
            ]);

        $created->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_id', $servico->id)
            ->assertJsonPath('data.date', '2026-09-01')
            ->assertJsonPath('data.time', '14:00')
            ->assertJsonPath('data.notes', 'Portão dos fundos');

        $agendaId = $created->json('data.id');

        $this->asUser($profissional)
            ->putJson("/api/v1/schedule/{$agendaId}", [
                'date' => '2026-09-02',
                'time' => '09:30',
                'notes' => 'Manhã',
            ])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-09-02')
            ->assertJsonPath('data.time', '09:30')
            ->assertJsonPath('data.notes', 'Manhã');

        $this->asUser($cliente)
            ->getJson('/api/v1/schedule')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $agendaId)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.page', 1)
            ->assertJsonPath('pagination.per_page', 20);

        $this->assertSame(1, Agenda::query()->count());
    }

    public function test_nao_agenda_nem_reagenda_servico_em_andamento(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();

        $this->asUser($cliente)
            ->postJson('/api/v1/schedule', [
                'service_id' => $servico->id,
                'date' => '2026-09-01',
                'time' => '14:00',
            ])
            ->assertCreated();

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/start")
            ->assertOk();

        $this->asUser($cliente)
            ->postJson('/api/v1/schedule', [
                'service_id' => $servico->id,
                'date' => '2026-09-02',
                'time' => '10:00',
            ])
            ->assertStatus(409);

        $agendaId = (string) Agenda::query()->value('id');

        $this->asUser($profissional)
            ->putJson("/api/v1/schedule/{$agendaId}", [
                'date' => '2026-09-02',
                'time' => '09:30',
            ])
            ->assertStatus(409);
    }

    public function test_nao_agenda_duas_vezes_o_mesmo_servico(): void
    {
        [$cliente, , $servico] = $this->contexto();
        $payload = [
            'service_id' => $servico->id,
            'date' => '2026-09-01',
            'time' => '14:00',
        ];

        $this->asUser($cliente)
            ->postJson('/api/v1/schedule', $payload)
            ->assertCreated();

        $this->asUser($cliente)
            ->postJson('/api/v1/schedule', $payload)
            ->assertStatus(409);
    }

    public function test_terceiro_nao_acessa_agenda_nem_chat(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();
        $intruso = Usuario::factory()->create();

        $this->asUser($cliente)
            ->postJson('/api/v1/schedule', [
                'service_id' => $servico->id,
                'date' => '2026-09-01',
                'time' => '14:00',
            ])
            ->assertCreated();

        $agendaId = (string) Agenda::query()->value('id');

        $this->asUser($intruso)
            ->postJson('/api/v1/schedule', [
                'service_id' => $servico->id,
                'date' => '2026-09-03',
                'time' => '10:00',
            ])
            ->assertForbidden();

        $this->asUser($intruso)
            ->putJson("/api/v1/schedule/{$agendaId}", [
                'date' => '2026-09-04',
                'time' => '11:00',
            ])
            ->assertForbidden();

        $this->asUser($intruso)
            ->getJson('/api/v1/schedule')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('pagination.total', 0);

        $this->asUser($intruso)
            ->getJson("/api/v1/services/{$servico->id}/messages")
            ->assertForbidden();

        $this->asUser($intruso)
            ->postJson("/api/v1/services/{$servico->id}/messages", ['text' => 'oi'])
            ->assertForbidden();

        $this->assertSame(0, Mensagem::query()->count());
        $this->assertSame($profissional->id, $servico->profissionalId());
    }

    public function test_chat_entre_cliente_e_profissional_com_paginacao(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/messages", [
                'text' => 'Posso confirmar o horário?',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_id', $servico->id)
            ->assertJsonPath('data.sender_id', $cliente->id)
            ->assertJsonPath('data.text', 'Posso confirmar o horário?')
            ->assertJsonPath('data.attachment', null);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/messages", [
                'text' => 'Sim, estarei lá.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_id', $profissional->id);

        Mensagem::factory()->create([
            'servico_id' => $servico->id,
            'remetente_id' => $cliente->id,
            'texto' => 'Terceira',
            'enviado_em' => now()->addMinute(),
        ]);

        $page1 = $this->asUser($cliente)
            ->getJson("/api/v1/services/{$servico->id}/messages?page=1&per_page=2");

        $page1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.page', 1)
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('pagination.last_page', 2);

        $this->assertCount(2, $page1->json('data'));
        $this->assertSame('Posso confirmar o horário?', $page1->json('data.0.text'));
        $this->assertSame('Sim, estarei lá.', $page1->json('data.1.text'));

        $this->asUser($profissional)
            ->getJson("/api/v1/services/{$servico->id}/messages?page=2&per_page=2")
            ->assertOk()
            ->assertJsonPath('pagination.page', 2)
            ->assertJsonPath('data.0.text', 'Terceira');
    }

    public function test_rotas_exigem_autenticacao(): void
    {
        [, , $servico] = $this->contexto();

        $this->postJson("/api/v1/services/{$servico->id}/start")->assertUnauthorized();
        $this->getJson("/api/v1/services/{$servico->id}/messages")->assertUnauthorized();
        $this->postJson("/api/v1/services/{$servico->id}/messages", ['text' => 'oi'])->assertUnauthorized();
        $this->getJson('/api/v1/schedule')->assertUnauthorized();
        $this->postJson('/api/v1/schedule', [
            'service_id' => $servico->id,
            'date' => '2026-09-01',
            'time' => '14:00',
        ])->assertUnauthorized();
        $this->putJson('/api/v1/schedule/'.fake()->uuid(), [
            'date' => '2026-09-01',
            'time' => '14:00',
        ])->assertUnauthorized();
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
            'inicio' => $status === StatusServico::EmAndamento ? now() : null,
        ]);

        return [$cliente, $profissional, $servico];
    }

    private function profissionalAtivo(): Usuario
    {
        return Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
        ]);
    }

    private function asUser(Usuario $usuario): static
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->actingAs($usuario, 'sanctum');
    }
}
