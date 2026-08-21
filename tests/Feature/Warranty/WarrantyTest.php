<?php

namespace Tests\Feature\Warranty;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\Jobs\CapturePaymentJob;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Actions\ApproveService;
use App\Services\Servico;
use App\Services\StatusServico;
use App\Users\Jobs\RecalcularPerfilConfiancaJob;
use App\Warranty\Actions\CloseWarranty;
use App\Warranty\Actions\IssueWarranty;
use App\Warranty\Garantia;
use App\Warranty\Jobs\IssueWarrantyJob;
use App\Warranty\ResponsavelFinanceiro;
use App\Warranty\StatusGarantia;
use App\Warranty\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
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
        [$cliente, $profissional, $servico] = $this->contextoAprovado();
        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        Queue::fake();

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

        // foundation/05-trust-level.md: GarantiaAcionada conta como
        // reclamação do profissional (reclamacoes_12m), mesmo em mediação.
        Queue::assertPushed(
            RecalcularPerfilConfiancaJob::class,
            fn (RecalcularPerfilConfiancaJob $job): bool => $job->profissionalId === $profissional->id,
        );
    }

    public function test_inv_053_acionar_e_encerrar_garantia_nao_cria_eventos_financeiros(): void
    {
        [$cliente, , $servico] = $this->contextoAprovado();
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

        $this->assertSame(StatusGarantia::Encerrada, $garantia->fresh()->status);
        $this->assertSame(0, $this->contagemTabelaFinanceira('payment_events'));
        $this->assertSame(0, $this->contagemTabelaFinanceira('payment_refunds'));
    }

    public function test_inv_033_revisita_nao_gera_nova_cobranca_nem_nova_garantia(): void
    {
        [$cliente, , $servico] = $this->contextoAprovado();
        $garantia = Garantia::factory()->create([
            'servico_id' => $servico->id,
        ]);

        $this->asUser($cliente)
            ->postJson("/api/v1/warranties/{$garantia->id}/claim", [
                'descricao' => 'O vazamento voltou no mesmo ponto.',
                'photos' => ['fotos/defeito-1.jpg'],
            ])
            ->assertOk();

        $revisita = Servico::query()->where('garantia_origem_id', $garantia->id)->first();
        $this->assertNotNull($revisita);
        $this->assertTrue($revisita->isRevisitaGarantia());
        $this->assertNull($revisita->proposta_id);
        $this->assertSame(0, $this->contagemTabelaFinanceira('payment_authorizations', $revisita->id));

        Queue::fake();
        $revisita->update([
            'status' => StatusServico::AguardandoAprovacao,
            'fim' => now(),
        ]);
        app(ApproveService::class)->byCliente($revisita->fresh(), $cliente);

        Queue::assertNotPushed(IssueWarrantyJob::class);
        Queue::assertNotPushed(CapturePaymentJob::class);

        (new IssueWarrantyJob($revisita->id))->handle(app(IssueWarranty::class));

        $herdada = app(IssueWarranty::class)($revisita->fresh());
        $this->assertSame($garantia->id, $herdada->id);
        $this->assertSame(1, Garantia::query()->count());
        $this->assertDatabaseHas('garantias', ['id' => $garantia->id, 'servico_id' => $servico->id]);
        $this->assertDatabaseMissing('garantias', ['servico_id' => $revisita->id]);
        $this->assertSame(0, $this->contagemTabelaFinanceira('payment_authorizations', $revisita->id));
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

    private function contagemTabelaFinanceira(string $tabela, ?string $servicoId = null): int
    {
        if (! Schema::hasTable($tabela)) {
            return 0;
        }

        $query = DB::table($tabela);

        if ($servicoId !== null && Schema::hasColumn($tabela, 'servico_id')) {
            $query->where('servico_id', $servicoId);
        }

        return $query->count();
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
