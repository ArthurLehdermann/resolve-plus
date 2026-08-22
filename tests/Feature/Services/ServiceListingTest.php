<?php

namespace Tests\Feature\Services;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\PaymentAuthorization;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Agenda;
use App\Services\Servico;
use App\Services\StatusServico;
use App\Warranty\Actions\CreateWarrantyRevisit;
use App\Warranty\Garantia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ServiceListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_traz_apenas_servicos_do_usuario(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();
        $this->contexto(); // serviço de outras pessoas, não pode aparecer.

        foreach ([$cliente, $profissional] as $usuario) {
            $this->asUser($usuario)
                ->getJson('/api/v1/services')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('pagination.total', 1)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $servico->id)
                ->assertJsonPath('data.0.client_id', $cliente->id)
                ->assertJsonPath('data.0.professional_id', $profissional->id);
        }
    }

    public function test_lista_filtra_por_status(): void
    {
        [$cliente, , $agendado] = $this->contexto();
        $emAndamento = Servico::factory()->create([
            'proposta_id' => Proposta::factory()->aceita()->create([
                'solicitacao_id' => Solicitacao::factory()->contratada()->create(['cliente_id' => $cliente->id])->id,
            ])->id,
            'status' => StatusServico::EmAndamento,
            'inicio' => now(),
        ]);

        $this->asUser($cliente)
            ->getJson('/api/v1/services?status=EM_ANDAMENTO')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $emAndamento->id);

        $this->asUser($cliente)
            ->getJson('/api/v1/services?status=AGENDADO')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $agendado->id);
    }

    public function test_lista_rejeita_status_invalido(): void
    {
        [$cliente] = $this->contexto();

        $this->asUser($cliente)
            ->getJson('/api/v1/services?status=INVENTADO')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_detalhe_traz_contexto_da_tela_de_execucao(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();
        Agenda::factory()->create(['servico_id' => $servico->id]);

        $this->asUser($cliente)
            ->getJson("/api/v1/services/{$servico->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $servico->id)
            ->assertJsonPath('data.status', StatusServico::Agendado->value)
            ->assertJsonPath('data.client_id', $cliente->id)
            ->assertJsonPath('data.professional_id', $profissional->id)
            ->assertJsonPath('data.proposal.id', $servico->proposta_id)
            ->assertJsonPath('data.request.id', $servico->proposta->solicitacao_id)
            ->assertJsonPath('data.schedule.service_id', $servico->id)
            ->assertJsonPath('data.payment.status', 'AUTORIZADO');
    }

    public function test_estranho_nao_ve_servico_alheio(): void
    {
        [, , $servico] = $this->contexto();

        $this->asUser($this->profissionalAtivo())
            ->getJson("/api/v1/services/{$servico->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_ve_qualquer_servico(): void
    {
        [, , $servico] = $this->contexto();
        $admin = Usuario::factory()->create(['tipo' => TipoUsuario::Admin]);

        $this->asUser($admin)
            ->getJson("/api/v1/services/{$servico->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $servico->id);
    }

    public function test_revisita_de_garantia_aparece_para_as_duas_partes(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto();
        $garantia = Garantia::factory()->create(['servico_id' => $servico->id]);
        $revisita = (new CreateWarrantyRevisit)($garantia);

        foreach ([$cliente, $profissional] as $usuario) {
            $ids = $this->asUser($usuario)
                ->getJson('/api/v1/services')
                ->assertOk()
                ->json('data.*.id');

            $this->assertContains($revisita->id, $ids);
        }

        $this->asUser($cliente)
            ->getJson("/api/v1/services/{$revisita->id}")
            ->assertOk()
            ->assertJsonPath('data.warranty_origin_id', $garantia->id)
            ->assertJsonPath('data.client_id', $cliente->id)
            ->assertJsonPath('data.professional_id', $profissional->id);
    }

    public function test_rotas_de_listagem_exigem_autenticacao(): void
    {
        [, , $servico] = $this->contexto();

        $this->getJson('/api/v1/services')->assertUnauthorized();
        $this->getJson("/api/v1/services/{$servico->id}")->assertUnauthorized();
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
        PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

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
