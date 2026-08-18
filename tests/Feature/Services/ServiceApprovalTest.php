<?php

namespace Tests\Feature\Services;

use App\Admin\Configuracao;
use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\Jobs\CapturePaymentJob;
use App\Payments\PaymentDispute;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\PropertyHistory\Intervention;
use App\PropertyHistory\OrigemIntervention;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Events\ServiceApproved;
use App\Services\Events\ServiceContested;
use App\Services\Events\ServiceFinished;
use App\Services\Servico;
use App\Services\StatusServico;
use App\Warranty\Jobs\IssueWarrantyJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_profissional_registra_conclusao_e_abre_janela_de_72h(): void
    {
        Event::fake([ServiceFinished::class]);

        [$cliente, $profissional, $servico] = $this->contexto(StatusServico::EmAndamento);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/finish", [
                'notes' => 'Serviço executado.',
                'photos' => ['evidencias/antes.jpg', 'evidencias/depois.jpg'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $servico->id)
            ->assertJsonPath('data.status', 'AGUARDANDO_APROVACAO')
            ->assertJsonPath('data.notes', 'Serviço executado.')
            ->assertJsonPath('data.photos.0', 'evidencias/antes.jpg');

        $servico->refresh();
        $this->assertSame(StatusServico::AguardandoAprovacao, $servico->status);
        $this->assertNotNull($servico->fim);
        $this->assertSame($cliente->id, $servico->clienteId());
        Event::assertDispatched(ServiceFinished::class);
    }

    public function test_cliente_nao_pode_registrar_conclusao(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::EmAndamento);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/finish", [
                'notes' => 'não deveria',
            ])
            ->assertForbidden();

        $this->assertSame(StatusServico::EmAndamento, $servico->fresh()->status);
    }

    public function test_nao_conclui_servico_fora_de_em_andamento(): void
    {
        [, $profissional, $servico] = $this->contexto(StatusServico::Agendado);

        $this->asUser($profissional)
            ->postJson("/api/v1/services/{$servico->id}/finish")
            ->assertStatus(409);
    }

    public function test_cliente_aprova_dispara_p4_p5_p7_e_e_idempotente(): void
    {
        Queue::fake();

        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);
        $key = (string) Str::uuid();

        $first = $this->asUser($cliente)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/services/{$servico->id}/approve");

        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'APROVADO');

        $this->assertSame(StatusServico::Aprovado, $servico->fresh()->status);
        $this->assertSame(1, Intervention::query()->where('servico_id', $servico->id)->count());

        $intervention = Intervention::query()->where('servico_id', $servico->id)->first();
        $this->assertNotNull($intervention);
        $this->assertSame(OrigemIntervention::Plataforma, $intervention->origem);
        $this->assertSame($servico->propertyId(), $intervention->asset->area->property_id);

        Queue::assertPushed(CapturePaymentJob::class, fn (CapturePaymentJob $job): bool => $job->servicoId === $servico->id);
        Queue::assertPushed(IssueWarrantyJob::class, fn (IssueWarrantyJob $job): bool => $job->servicoId === $servico->id);

        $second = $this->asUser($cliente)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/services/{$servico->id}/approve");

        $second->assertOk()
            ->assertJsonPath('data.status', 'APROVADO');

        $this->assertSame(1, Intervention::query()->where('servico_id', $servico->id)->count());
        Queue::assertPushed(CapturePaymentJob::class, 1);
        Queue::assertPushed(IssueWarrantyJob::class, 1);
    }

    public function test_approve_exige_idempotency_key(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(StatusServico::AguardandoAprovacao, $servico->fresh()->status);
    }

    public function test_profissional_nao_pode_aprovar(): void
    {
        [, $profissional, $servico] = $this->contexto(StatusServico::AguardandoAprovacao);

        $this->asUser($profissional)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/approve")
            ->assertForbidden();
    }

    public function test_cliente_contesta_e_e_idempotente(): void
    {
        Event::fake([ServiceContested::class, ServiceApproved::class]);

        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);
        $key = (string) Str::uuid();
        $payload = ['motivo' => 'Serviço incompleto no banheiro.'];

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/services/{$servico->id}/contest", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_CONTESTACAO');

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/services/{$servico->id}/contest", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_CONTESTACAO');

        $this->assertSame(StatusServico::EmContestacao, $servico->fresh()->status);
        $this->assertSame(1, PaymentDispute::query()->count());

        $dispute = PaymentDispute::query()->first();
        $this->assertNotNull($dispute);
        $this->assertSame(TipoPaymentDispute::ContestacaoConclusao, $dispute->tipo);
        $this->assertSame(StatusPaymentDispute::Aberta, $dispute->status);
        $this->assertSame('Serviço incompleto no banheiro.', $dispute->motivo);
        Event::assertDispatchedTimes(ServiceContested::class, 1);
        Event::assertNotDispatched(ServiceApproved::class);
        $this->assertSame(0, Intervention::query()->count());
    }

    public function test_contest_exige_idempotency_key(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/contest", [
                'motivo' => 'não ficou bom',
            ])
            ->assertStatus(422);

        $this->assertSame(StatusServico::AguardandoAprovacao, $servico->fresh()->status);
        $this->assertSame(0, PaymentDispute::query()->count());
    }

    public function test_job_aprova_automaticamente_apos_janela_da_configuracao(): void
    {
        Queue::fake();
        $this->freezeTime();

        [, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);
        $this->assertSame(72, Configuracao::inteiro('AUTO_APPROVAL_HOURS'));

        $this->travel(71)->hours();
        $this->artisan('services:auto-approve')->assertSuccessful();
        $this->assertSame(StatusServico::AguardandoAprovacao, $servico->fresh()->status);

        $this->travel(1)->hours();
        $this->artisan('services:auto-approve')
            ->expectsOutputToContain('Serviços aprovados automaticamente: 1')
            ->assertSuccessful();

        $this->assertSame(StatusServico::Aprovado, $servico->fresh()->status);
        $this->assertSame(1, Intervention::query()->where('servico_id', $servico->id)->count());
        Queue::assertPushed(CapturePaymentJob::class, 1);
        Queue::assertPushed(IssueWarrantyJob::class, 1);
    }

    public function test_job_le_auto_approval_hours_da_configuracao_nao_hardcoded(): void
    {
        Queue::fake();
        $this->freezeTime();

        Configuracao::query()->whereKey('AUTO_APPROVAL_HOURS')->update(['valor' => '2']);

        [, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);

        $this->travel(1)->hours();
        $this->artisan('services:auto-approve')->assertSuccessful();
        $this->assertSame(StatusServico::AguardandoAprovacao, $servico->fresh()->status);

        $this->travel(1)->hours();
        $this->artisan('services:auto-approve')->assertSuccessful();
        $this->assertSame(StatusServico::Aprovado, $servico->fresh()->status);
    }

    public function test_contestacao_bloqueia_aceite_automatico(): void
    {
        $this->freezeTime();

        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/contest", [
                'motivo' => 'Não foi o combinado.',
            ])
            ->assertOk();

        $this->travel(72)->hours();
        $this->artisan('services:auto-approve')->assertSuccessful();

        $this->assertSame(StatusServico::EmContestacao, $servico->fresh()->status);
        $this->assertSame(0, Intervention::query()->count());
        $this->assertSame(1, PaymentDispute::query()->where('status', 'ABERTA')->count());
    }

    public function test_rotas_exigem_autenticacao(): void
    {
        [, , $servico] = $this->contexto(StatusServico::EmAndamento);

        $this->postJson("/api/v1/services/{$servico->id}/finish")->assertUnauthorized();
        $this->postJson("/api/v1/services/{$servico->id}/approve")->assertUnauthorized();
        $this->postJson("/api/v1/services/{$servico->id}/contest", ['motivo' => 'x'])->assertUnauthorized();
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

        $attributes = [
            'proposta_id' => $proposta->id,
            'status' => $status,
        ];

        if ($status === StatusServico::EmAndamento) {
            $attributes['inicio'] = now();
        }

        if ($status === StatusServico::AguardandoAprovacao) {
            $attributes['inicio'] = now()->subHour();
            $attributes['fim'] = now();
        }

        $servico = Servico::factory()->create($attributes);

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
