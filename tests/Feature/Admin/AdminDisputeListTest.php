<?php

namespace Tests\Feature\Admin;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\ResultadoPaymentDispute;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDisputeListTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_traz_o_contexto_necessario_para_mediar(): void
    {
        $token = $this->adminToken();
        $servico = $this->servicoEmContestacao();
        $dispute = PaymentDispute::factory()->create([
            'servico_id' => $servico->id,
            'tipo' => TipoPaymentDispute::ContestacaoConclusao,
            'status' => StatusPaymentDispute::Aberta,
            'motivo' => 'Serviço entregue pela metade.',
        ]);
        PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/disputes');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.data.0.id', $dispute->id)
            ->assertJsonPath('data.data.0.motivo', 'Serviço entregue pela metade.')
            ->assertJsonPath('data.data.0.servico.id', $servico->id)
            ->assertJsonPath('data.data.0.servico.status', StatusServico::EmContestacao->value);

        $item = $response->json('data.data.0');

        // Sem as partes e o valor o admin não tem como julgar o mérito.
        $this->assertNotNull($item['servico']['cliente']['nome']);
        $this->assertNotNull($item['servico']['profissional']['nome']);
        $this->assertNotNull($item['servico']['categoria']);
        $this->assertNotNull($item['servico']['valor']);
        $this->assertNotNull($item['servico']['pagamento']['status']);
        // Prazo do timeout automático (DISPUTE_MEDIATION_DAYS).
        $this->assertNotNull($item['prazo_em']);
    }

    public function test_filtra_por_status_e_poe_abertas_no_topo(): void
    {
        $token = $this->adminToken();

        $resolvida = PaymentDispute::factory()->create([
            'servico_id' => $this->servicoEmContestacao()->id,
            'status' => StatusPaymentDispute::Resolvida,
            'resultado' => ResultadoPaymentDispute::Aprovado,
            'aberta_em' => now()->subDays(3),
            'resolvida_em' => now()->subDay(),
        ]);
        $aberta = PaymentDispute::factory()->create([
            'servico_id' => $this->servicoEmContestacao()->id,
            'status' => StatusPaymentDispute::Aberta,
            'aberta_em' => now()->subDays(5),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/disputes')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            // Aberta primeiro, mesmo sendo a mais antiga.
            ->assertJsonPath('data.data.0.id', $aberta->id);

        $this->withToken($token)
            ->getJson('/api/v1/admin/disputes?status=RESOLVIDA')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.data.0.id', $resolvida->id)
            ->assertJsonPath('data.data.0.resultado', ResultadoPaymentDispute::Aprovado->value)
            // Resolvida não tem prazo de timeout para correr.
            ->assertJsonPath('data.data.0.prazo_em', null);
    }

    public function test_status_invalido_responde_422(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/disputes?status=SEI_LA')
            ->assertStatus(422);
    }

    public function test_nao_admin_nao_lista_disputas(): void
    {
        $usuario = Usuario::factory()->create([
            'tipo' => TipoUsuario::Cliente->value,
            'status' => StatusConta::Ativa,
        ]);

        $this->withToken($usuario->createToken('auth')->plainTextToken)
            ->getJson('/api/v1/admin/disputes')
            ->assertForbidden();
    }

    private function adminToken(): string
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);

        return $admin->createToken('auth')->plainTextToken;
    }

    private function servicoEmContestacao(): Servico
    {
        $solicitacao = Solicitacao::factory()->contratada()->create();
        $proposta = Proposta::factory()->aceita()->create(['solicitacao_id' => $solicitacao->id]);

        return Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::EmContestacao,
        ]);
    }
}
