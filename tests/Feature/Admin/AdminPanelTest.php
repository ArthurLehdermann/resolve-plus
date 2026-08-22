<?php

namespace Tests\Feature\Admin;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\PaymentAuthorization;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Services\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_endpoints_return_403_for_non_admin_authenticated_users(): void
    {
        $usuario = Usuario::factory()->create([
            'tipo' => TipoUsuario::Cliente->value,
            'status' => StatusConta::Ativa,
        ]);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/services')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/payments')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/professionals/documents')->assertForbidden();
    }

    public function test_get_admin_users_returns_paginated_users(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        Usuario::factory()->count(25)->create([
            'tipo' => TipoUsuario::Cliente->value,
            'status' => StatusConta::Ativa,
        ]);

        $total = Usuario::query()->count();
        $perPage = 20;

        $response = $this->withToken($adminToken)
            ->getJson('/api/v1/admin/users?page=1&per_page='.$perPage);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [],
                    'pagination' => [
                        'page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $this->assertCount($perPage, $response->json('data.data'));
        $this->assertSame($total, $response->json('data.pagination.total'));
        $this->assertSame(1, $response->json('data.pagination.page'));
        $this->assertSame($perPage, $response->json('data.pagination.per_page'));
        $this->assertSame((int) ceil($total / $perPage), $response->json('data.pagination.last_page'));
    }

    public function test_get_admin_services_returns_paginated_servicos(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        $servicos = Servico::factory()->count(3)->create();

        $response = $this->withToken($adminToken)
            ->getJson('/api/v1/admin/services?page=1&per_page=2');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        ['id', 'proposal_id', 'status'],
                    ],
                    'pagination' => [
                        'page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertSame(3, $response->json('data.pagination.total'));
        $this->assertSame(1, $response->json('data.pagination.page'));
        $this->assertSame(2, $response->json('data.pagination.per_page'));
        $this->assertSame(2, $response->json('data.pagination.last_page'));
        $this->assertEmpty(array_diff($ids, $servicos->pluck('id')->all()));
    }

    public function test_get_admin_payments_returns_paginated_authorizations(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        $authorizations = PaymentAuthorization::factory()->count(2)->create();

        $response = $this->withToken($adminToken)
            ->getJson('/api/v1/admin/payments?page=1&per_page=20');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        ['id', 'servico_id', 'valor', 'metodo', 'status'],
                    ],
                    'pagination' => [
                        'page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertSame(2, $response->json('data.pagination.total'));
        $this->assertEqualsCanonicalizing($authorizations->pluck('id')->all(), $ids);
    }

    public function test_get_admin_documents_returns_paginated_documentos_with_profissional(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        $profissional = Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional->value,
            'status' => StatusConta::Ativa,
        ]);
        DocumentoProfissional::factory()->count(2)->create([
            'profissional_id' => $profissional->id,
            'status' => StatusDocumentoProfissional::Pendente->value,
        ]);
        DocumentoProfissional::factory()->create([
            'status' => StatusDocumentoProfissional::Aprovado->value,
        ]);

        $response = $this->withToken($adminToken)
            ->getJson('/api/v1/admin/professionals/documents?page=1&per_page=20');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        ['id', 'profissional_id', 'profissional' => ['id', 'nome', 'email'], 'tipo', 'status'],
                    ],
                    'pagination' => [
                        'page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $this->assertSame(3, $response->json('data.pagination.total'));
    }

    public function test_get_admin_documents_filters_by_status(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        DocumentoProfissional::factory()->count(2)->create([
            'status' => StatusDocumentoProfissional::Pendente->value,
        ]);
        DocumentoProfissional::factory()->create([
            'status' => StatusDocumentoProfissional::Aprovado->value,
        ]);

        $response = $this->withToken($adminToken)
            ->getJson('/api/v1/admin/professionals/documents?status='.StatusDocumentoProfissional::Pendente->value);

        $response->assertOk();
        $this->assertSame(2, $response->json('data.pagination.total'));
        $statuses = collect($response->json('data.data'))->pluck('status')->all();
        $this->assertSame([StatusDocumentoProfissional::Pendente->value, StatusDocumentoProfissional::Pendente->value], $statuses);
    }

    public function test_get_admin_dashboard_returns_general_indicators_and_leakage_metrics(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        $response = $this->withToken($adminToken)->getJson('/api/v1/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'general_indicators' => [
                        'total_usuarios',
                    ],
                    'leakage_metrics' => [
                        'tentativas_pre_aceite',
                        'tentativas_pos_aceite',
                        'taxa_conclusao_pos_tentativa',
                    ],
                ],
            ]);
    }
}
