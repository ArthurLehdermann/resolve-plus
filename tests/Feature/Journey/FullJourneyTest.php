<?php

namespace Tests\Feature\Journey;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\Payments\PaymentAuthorization;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\TipoPaymentEvent;
use App\PropertyHistory\Intervention;
use App\Warranty\Garantia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ponta a ponta: registro -> imóvel -> solicitação -> proposta -> aceite (com
 * autorização de pagamento) -> agenda -> execução -> aprovação (captura +
 * garantia + prontuário) -> avaliação -> histórico do imóvel.
 *
 * Não re-testa verificação documental do profissional (tem cobertura própria
 * em outro lugar) - o profissional já nasce ATIVA aqui, de propósito.
 */
class FullJourneyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O guard sanctum cacheia o usuário resolvido entre chamadas simuladas
     * dentro do mesmo teste; sem isso, trocar de cliente pra profissional no
     * meio do teste reaproveitaria o ator anterior.
     */
    private function asToken(string $token): static
    {
        Auth::forgetGuards();

        return $this->withToken($token);
    }

    public function test_jornada_completa_do_registro_a_captura(): void
    {
        // 1. Registro do cliente e do profissional.
        $clienteData = $this->postJson('/api/v1/auth/register', [
            'nome' => 'Cliente Jornada',
            'email' => 'cliente-jornada@teste.com',
            'telefone' => '11999990000',
            'senha' => 'Senha@123',
            'tipo' => 'CLIENTE',
        ])->assertCreated()->json('data');
        $clienteToken = $clienteData['token'];

        $profissionalData = $this->postJson('/api/v1/auth/register', [
            'nome' => 'Profissional Jornada',
            'email' => 'profissional-jornada@teste.com',
            'telefone' => '11988880000',
            'senha' => 'Senha@123',
            'tipo' => 'PROFISSIONAL',
        ])->assertCreated()->json('data');
        $profissionalToken = $profissionalData['token'];

        // Verificação documental já coberta em outro teste - ativa direto.
        Usuario::query()->whereKey($profissionalData['user']['id'])
            ->update(['status' => StatusConta::Ativa->value]);

        // 2. Imóvel do cliente.
        $property = $this->asToken($clienteToken)
            ->postJson('/api/v1/properties', [
                'cep' => '01310-200',
                'logradouro' => 'Avenida Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'apelido' => 'Casa da jornada',
            ])
            ->assertCreated()
            ->json('data');

        // 3. Solicitação de serviço.
        $categoria = Categoria::factory()->mvp('pintura')->create();

        $solicitacao = $this->asToken($clienteToken)
            ->postJson('/api/v1/requests', [
                'property_id' => $property['id'],
                'category_id' => $categoria->id,
                'description' => 'Pintura da sala e corredor',
                'scope' => [
                    'comodos' => 2,
                    'area_m2' => 35.5,
                    'tipo_tinta' => 'LATEX_PVA',
                    'paredes_ou_teto' => 'PAREDES_E_TETO',
                ],
            ])
            ->assertCreated()
            ->json('data');

        // 4. Profissional envia proposta.
        $proposta = $this->asToken($profissionalToken)
            ->postJson("/api/v1/requests/{$solicitacao['id']}/proposals", [
                'price' => 60000,
                'deadline_days' => 5,
                'warranty_days' => 90,
                'notes' => 'Posso começar na semana que vem.',
            ])
            ->assertCreated()
            ->json('data');

        // 5. Cliente aceita com Pix - cria a autorização de pagamento (INV-C1)
        // e já confirma a cobrança na hora.
        $aceite = $this->asToken($clienteToken)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/proposals/{$proposta['id']}/accept", [
                'metodo_pagamento' => 'PIX',
            ])
            ->assertCreated()
            ->json('data');

        $servicoId = $aceite['service']['id'];
        $this->assertSame('AGENDADO', $aceite['service']['status']);

        $authorization = PaymentAuthorization::query()->where('servico_id', $servicoId)->firstOrFail();
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);
        $this->assertSame(60000, $authorization->valor);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Capturado));

        // 6. Agenda.
        $this->asToken($clienteToken)
            ->postJson('/api/v1/schedule', [
                'service_id' => $servicoId,
                'date' => now()->addDays(3)->toDateString(),
                'time' => '09:00',
            ])
            ->assertCreated();

        // 7. Profissional inicia e conclui a execução.
        $this->asToken($profissionalToken)
            ->postJson("/api/v1/services/{$servicoId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_ANDAMENTO');

        $this->asToken($profissionalToken)
            ->postJson("/api/v1/services/{$servicoId}/finish", [
                'notes' => 'Pintura concluída, duas demãos.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'AGUARDANDO_APROVACAO');

        // 8. Cliente aprova - dispara captura (Pix já capturado, é no-op),
        // emissão de garantia e registro no prontuário do imóvel.
        $this->asToken($clienteToken)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servicoId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APROVADO');

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);

        $garantia = Garantia::query()->where('servico_id', $servicoId)->first();
        $this->assertNotNull($garantia, 'Garantia deveria ter sido emitida na aprovação.');

        $intervention = Intervention::query()->where('servico_id', $servicoId)->first();
        $this->assertNotNull($intervention, 'Intervenção deveria ter sido registrada no prontuário.');

        // 9. Cliente avalia o profissional.
        $this->asToken($clienteToken)
            ->postJson("/api/v1/services/{$servicoId}/rating", [
                'score' => 5,
                'comment' => 'Serviço excelente.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nota', 5);

        // 10. Prontuário do imóvel reflete o serviço aprovado (fecha o ciclo
        // com o endpoint que era o A1: exige auth + posse, dono vê o histórico).
        $this->asToken($clienteToken)
            ->getJson("/api/v1/properties/{$property['id']}/history")
            ->assertOk()
            ->assertJsonPath('data.property_id', $property['id'])
            ->assertJsonPath('data.areas.0.assets.0.interventions.0.servico_id', $servicoId);
    }
}
