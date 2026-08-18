<?php

namespace Tests\Feature\Admin;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
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

    public function test_get_admin_services_payments_returns_empty_with_pagination_structure(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin->value,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        $this->withToken($adminToken)
            ->getJson('/api/v1/admin/services?page=2&per_page=10')
            ->assertOk()
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

        $this->withToken($adminToken)
            ->getJson('/api/v1/admin/payments?page=2&per_page=10')
            ->assertOk()
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
