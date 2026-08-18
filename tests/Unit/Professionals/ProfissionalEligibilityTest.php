<?php

namespace Tests\Unit\Professionals;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Professionals\Services\ProfissionalEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfissionalEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inv_002_only_active_profissional_can_receive_requests(): void
    {
        $profissionalAtivo = Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
        ]);
        $profissionalPendente = Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::PendenteVerificacao,
        ]);
        $cliente = Usuario::factory()->create([
            'tipo' => TipoUsuario::Cliente,
            'status' => StatusConta::Ativa,
        ]);

        $this->assertTrue(ProfissionalEligibility::podeReceberSolicitacoes($profissionalAtivo));
        $this->assertFalse(ProfissionalEligibility::podeReceberSolicitacoes($profissionalPendente));
        $this->assertFalse(ProfissionalEligibility::podeReceberSolicitacoes($cliente));
    }
}
