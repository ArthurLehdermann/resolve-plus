<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private static function senhaValida(string $sufixo = 'ok'): string
    {
        return implode('-', ['Aa1', 'teste', $sufixo]);
    }

    public function test_register_creates_cliente_with_status_ativa_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'tipo' => 'CLIENTE',
            'nome' => 'Maria Cliente',
            'email' => 'maria@example.com',
            'telefone' => '11999990000',
            'senha' => self::senhaValida(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.tipo', 'CLIENTE')
            ->assertJsonPath('data.user.status', 'ATIVA')
            ->assertJsonStructure(['data' => ['user' => ['id', 'nome', 'email'], 'token']]);

        $usuario = Usuario::query()->where('email', 'maria@example.com')->first();
        $this->assertNotNull($usuario);
        $this->assertSame(TipoUsuario::Cliente, $usuario->tipo);
        $this->assertSame(StatusConta::Ativa, $usuario->status);
        $this->assertTrue(str_starts_with($usuario->senha_hash, '$argon2id$'));
    }

    public function test_register_creates_profissional_with_status_pendente_verificacao(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'tipo' => 'PROFISSIONAL',
            'nome' => 'João Profissional',
            'email' => 'joao@example.com',
            'telefone' => '11988880000',
            'senha' => self::senhaValida(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.tipo', 'PROFISSIONAL')
            ->assertJsonPath('data.user.status', 'PENDENTE_VERIFICACAO');
    }

    public function test_register_rejects_admin_tipo_and_duplicate_email(): void
    {
        Usuario::factory()->create(['email' => 'duplicado@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'tipo' => 'ADMIN',
            'nome' => 'Admin',
            'email' => 'admin@example.com',
            'telefone' => '11977770000',
            'senha' => self::senhaValida(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo']);

        $this->postJson('/api/v1/auth/register', [
            'tipo' => 'PROFISSIONAL',
            'nome' => 'Outro Usuário',
            'email' => 'duplicado@example.com',
            'telefone' => '11966660000',
            'senha' => self::senhaValida(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_inv_001_tipo_is_fixed_per_account_without_hybrid_support(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'tipo' => 'CLIENTE',
            'nome' => 'Conta Cliente',
            'email' => 'cliente-fixo@example.com',
            'telefone' => '11955550000',
            'senha' => self::senhaValida(),
        ])->assertCreated();

        $cliente = Usuario::query()->where('email', 'cliente-fixo@example.com')->firstOrFail();
        $this->assertSame(TipoUsuario::Cliente, $cliente->tipo);

        $this->postJson('/api/v1/auth/register', [
            'tipo' => 'PROFISSIONAL',
            'nome' => 'Conta Profissional',
            'email' => 'profissional-fixo@example.com',
            'telefone' => '11944440000',
            'senha' => self::senhaValida(),
        ])->assertCreated();

        $profissional = Usuario::query()->where('email', 'profissional-fixo@example.com')->firstOrFail();
        $this->assertSame(TipoUsuario::Profissional, $profissional->tipo);
        $this->assertNotSame($cliente->tipo, $profissional->tipo);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $senha = self::senhaValida();
        $usuario = Usuario::factory()->create([
            'email' => 'login@example.com',
            'senha_hash' => $senha,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'senha' => $senha,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $usuario->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        Usuario::factory()->create([
            'email' => 'login@example.com',
            'senha_hash' => self::senhaValida(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'senha' => self::senhaValida('errada'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_invalidates_current_token(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $usuario->tokens()->count());
        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_forgot_password_accepts_registered_email(): void
    {
        Usuario::factory()->create(['email' => 'recuperar@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'recuperar@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_reset_password_updates_hash_and_invalidates_tokens(): void
    {
        $senhaAntiga = self::senhaValida('antiga');
        $senhaNova = self::senhaValida('nova');
        $usuario = Usuario::factory()->create([
            'email' => 'reset@example.com',
            'senha_hash' => $senhaAntiga,
        ]);
        $token = $usuario->createToken('auth')->plainTextToken;
        $resetToken = Password::createToken($usuario);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $resetToken,
            'senha' => $senhaNova,
            'senha_confirmation' => $senhaNova,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $usuario->refresh();
        $this->assertTrue(Hash::check($senhaNova, $usuario->senha_hash));
        $this->assertFalse(Hash::check($senhaAntiga, $usuario->senha_hash));

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function test_authenticated_routes_require_bearer_token(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }
}
