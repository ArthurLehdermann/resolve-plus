<?php

namespace Tests\Feature\Warranty;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\CreatePaymentAuthorization;
use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentEvent;
use App\Payments\PaymentRefund;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\TipoPaymentEvent;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use App\Warranty\Actions\CloseWarranty;
use App\Warranty\Actions\IssueWarranty;
use App\Warranty\Garantia;
use App\Warranty\Jobs\IssueWarrantyJob;
use App\Warranty\ResponsavelFinanceiro;
use App\Warranty\StatusGarantia;
use App\Warranty\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    public function test_inv_050_051_emite_garantia_ativa_com_prazo_da_proposta(): void
    {
        $this->freezeTime();

        [, , $servico] = $this->contextoAprovado(garantiaDias: 60);

        $garantia = app(IssueWarranty::class)($servico);

        $this->assertSame(StatusGarantia::Ativa, $garantia->status);
        $this->assertSame(ResponsavelFinanceiro::Profissional, $garantia->responsavel_financeiro);
        $this->assertSame(60, (int) $garantia->inicio->diffInDays($garantia->fim));
        $this->assertSame(1, Garantia::query()->where('servico_id', $servico->id)->count());

        $segunda = app(IssueWarranty::class)($servico->fresh());
        $this->assertSame($garantia->id, $segunda->id);
    }

    public function test_job_emite_garantia_apos_aprovacao(): void
    {
        [, , $servico] = $this->contextoAprovado();

        (new IssueWarrantyJob($servico->id))->handle(app(IssueWarranty::class));

        $this->assertDatabaseHas('garantias', [
            'servico_id' => $servico->id,
            'status' => StatusGarantia::Ativa->value,
            'responsavel_financeiro' => ResponsavelFinanceiro::Profissional->value,
        ]);
    }

    public function test_get_warranties_lista_para_cliente_e_profissional(): void
    {
        [$cliente, $profissional, $servico] = $this->contextoAprovado();
        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        $this->asUser($cliente)
            ->getJson('/api/v1/warranties')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $garantia->id);

        $this->asUser($profissional)
            ->getJson('/api/v1/warranties')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $garantia->id);
    }

    public function test_get_warranty_show_inclui_claims(): void
    {
        [$cliente, , $servico] = $this->contextoAprovado();
        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        WarrantyClaim::query()->create([
            'garantia_id' => $garantia->id,
            'descricao' => 'Vazamento retornou após uma semana.',
            'photos' => ['fotos/defeito.jpg'],
        ]);

        $this->asUser($cliente)
            ->getJson("/api/v1/warranties/{$garantia->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $garantia->id)
            ->assertJsonPath('data.responsavel_financeiro', 'PROFISSIONAL')
            ->assertJsonCount(1, 'data.claims')
            ->assertJsonPath('data.claims.0.descricao', 'Vazamento retornou após uma semana.');
    }

    public function test_claim_exige_evidencias(): void
    {
        [$cliente, , $servico] = $this->contextoAprovado();
        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        $this->asUser($cliente)
            ->postJson("/api/v1/warranties/{$garantia->id}/claim", [])
            ->assertStatus(422);

        $this->asUser($cliente)
            ->postJson("/api/v1/warranties/{$garantia->id}/claim", [
                'descricao' => 'curta',
                'photos' => [],
            ])
            ->assertStatus(422);

        $this->assertSame(StatusGarantia::Ativa, $garantia->fresh()->status);
        $this->assertSame(0, WarrantyClaim::query()->count());
    }

    public function test_claim_aciona_garantia_com_evidencias(): void
    {
        [$cliente, , $servico] = $this->contextoAprovado();
        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        $this->asUser($cliente)
            ->postJson("/api/v1/warranties/{$garantia->id}/claim", [
                'descricao' => 'O vazamento voltou no mesmo ponto.',
                'photos' => ['fotos/defeito-1.jpg', 'fotos/defeito-2.jpg'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ACIONADA')
            ->assertJsonCount(1, 'data.claims');

        $garantia->refresh();
        $this->assertSame(StatusGarantia::Acionada, $garantia->status);
        $this->assertSame(1, Servico::query()->where('garantia_origem_id', $garantia->id)->count());
    }

    public function test_inv_053_acionar_e_encerrar_garantia_nao_cria_eventos_financeiros(): void
    {
        [$cliente, , $servico] = $this->contextoAprovado();
        $authorization = PaymentAuthorization::query()->create([
            'servico_id' => $servico->id,
            'valor' => 10_000,
            'metodo' => MetodoPagamento::Cartao,
            'status' => StatusPaymentAuthorization::Capturado,
            'expira_em' => null,
        ]);

        PaymentEvent::query()->create([
            'payment_authorization_id' => $authorization->id,
            'tipo' => TipoPaymentEvent::Capturado,
            'payload' => ['motivo' => 'SERVICO_APROVADO'],
        ]);

        $eventsBefore = PaymentEvent::query()->count();
        $refundsBefore = PaymentRefund::query()->count();

        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        $this->asUser($cliente)
            ->postJson("/api/v1/warranties/{$garantia->id}/claim", [
                'descricao' => 'Problema recorrente no serviço executado.',
                'photos' => ['fotos/problema.jpg'],
            ])
            ->assertOk();

        app(CloseWarranty::class)($garantia->fresh());

        $this->assertSame($eventsBefore, PaymentEvent::query()->count());
        $this->assertSame($refundsBefore, PaymentRefund::query()->count());
    }

    public function test_inv_033_revisita_nao_gera_nova_payment_authorization(): void
    {
        [, , $servico] = $this->contextoAprovado();
        PaymentAuthorization::query()->create([
            'servico_id' => $servico->id,
            'valor' => 10_000,
            'metodo' => MetodoPagamento::Cartao,
            'status' => StatusPaymentAuthorization::Capturado,
            'expira_em' => null,
        ]);

        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        $revisita = Servico::query()->create([
            'proposta_id' => null,
            'garantia_origem_id' => $garantia->id,
            'status' => StatusServico::Agendado,
        ]);

        $creator = app(CreatePaymentAuthorization::class);
        $this->assertNull($creator->forServico($revisita, 10_000, MetodoPagamento::Cartao));
        $this->assertSame(1, PaymentAuthorization::query()->count());

        $segunda = $creator->forServico($revisita->fresh(), 10_000, MetodoPagamento::Cartao);
        $this->assertNull($segunda);
        $this->assertSame(1, PaymentAuthorization::query()->count());
    }

    public function test_rotas_de_garantia_exigem_autenticacao(): void
    {
        [, , $servico] = $this->contextoAprovado();
        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        $this->getJson('/api/v1/warranties')->assertUnauthorized();
        $this->getJson("/api/v1/warranties/{$garantia->id}")->assertUnauthorized();
        $this->postJson("/api/v1/warranties/{$garantia->id}/claim", [
            'descricao' => 'Problema no serviço.',
            'photos' => ['foto.jpg'],
        ])->assertUnauthorized();
    }

    /**
     * @return array{0: Usuario, 1: Usuario, 2: Servico}
     */
    private function contextoAprovado(int $garantiaDias = 90): array
    {
        $cliente = Usuario::factory()->create();
        $profissional = Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
        ]);
        $solicitacao = Solicitacao::factory()->contratada()->create([
            'cliente_id' => $cliente->id,
        ]);
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
            'garantia_dias' => $garantiaDias,
        ]);
        $servico = Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::Aprovado,
        ]);

        return [$cliente, $profissional, $servico];
    }

    private function asUser(Usuario $usuario): static
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->actingAs($usuario, 'sanctum');
    }
}
