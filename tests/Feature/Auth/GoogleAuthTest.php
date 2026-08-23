<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'client-id-de-teste',
            'services.google.client_secret' => 'client-secret-de-teste',
            'services.google.redirect' => 'https://api.teste/api/v1/auth/google/callback',
            'app.frontend_url' => 'https://app.teste',
        ]);
    }

    public function test_redirect_manda_para_o_google_com_o_client_id(): void
    {
        $response = $this->get('/api/v1/auth/google/redirect');

        $response->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
        $response->assertRedirectContains('client-id-de-teste');
    }

    public function test_sem_credenciais_configuradas_responde_503(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->getJson('/api/v1/auth/google/redirect')->assertStatus(503);
    }

    public function test_callback_cria_conta_e_app_troca_codigo_por_token(): void
    {
        $this->fakeGoogle('novo@example.com', 'Fulano do Google');

        $state = $this->stateValido();

        $callback = $this->get('/api/v1/auth/google/callback?code=abc&state='.$state);

        $callback->assertRedirectContains('https://app.teste/auth/google?codigo=');

        $codigo = $this->codigoDoRedirect($callback->headers->get('Location'));

        $exchange = $this->postJson('/api/v1/auth/google/exchange', ['codigo' => $codigo]);

        $exchange->assertOk()
            ->assertJsonPath('data.user.email', 'novo@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $usuario = Usuario::query()->comEmail('novo@example.com')->firstOrFail();
        $this->assertSame(TipoUsuario::Cliente, $usuario->tipo);
        $this->assertSame(StatusConta::Ativa, $usuario->status);
        $this->assertNull($usuario->senha_hash);

        // Código de troca é de uso único.
        $this->postJson('/api/v1/auth/google/exchange', ['codigo' => $codigo])
            ->assertUnprocessable();
    }

    public function test_conta_existente_entra_sem_virar_outro_tipo(): void
    {
        $profissional = Usuario::factory()->profissionalAtivo()->create(['email' => 'ja-existe@example.com']);
        $this->fakeGoogle('ja-existe@example.com', 'Já Existe');

        $callback = $this->get('/api/v1/auth/google/callback?code=abc&state='.$this->stateValido('CLIENTE'));
        $codigo = $this->codigoDoRedirect($callback->headers->get('Location'));

        $this->postJson('/api/v1/auth/google/exchange', ['codigo' => $codigo])
            ->assertOk()
            ->assertJsonPath('data.user.id', $profissional->id)
            ->assertJsonPath('data.user.tipo', 'PROFISSIONAL');

        $this->assertSame(1, Usuario::query()->comEmail('ja-existe@example.com')->count());
    }

    public function test_state_invalido_nao_vira_sessao(): void
    {
        $this->fakeGoogle('qualquer@example.com', 'Qualquer');

        $this->get('/api/v1/auth/google/callback?code=abc&state=forjado')
            ->assertRedirectContains('erro=state_invalido');

        $this->assertSame(0, Usuario::query()->count());
    }

    public function test_email_nao_verificado_no_google_e_recusado(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-google']),
            'googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'naoverificado@example.com',
                'email_verified' => false,
                'name' => 'Não Verificado',
            ]),
        ]);

        $this->get('/api/v1/auth/google/callback?code=abc&state='.$this->stateValido())
            ->assertRedirectContains('erro=falha_google');

        $this->assertSame(0, Usuario::query()->count());
    }

    public function test_conta_suspensa_nao_entra_pelo_google(): void
    {
        Usuario::factory()->create([
            'email' => 'suspensa@example.com',
            'status' => StatusConta::Suspensa,
        ]);
        $this->fakeGoogle('suspensa@example.com', 'Suspensa');

        $this->get('/api/v1/auth/google/callback?code=abc&state='.$this->stateValido())
            ->assertRedirectContains('erro=conta_suspensa');
    }

    private function fakeGoogle(string $email, string $nome): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-google']),
            'googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => $email,
                'email_verified' => true,
                'name' => $nome,
                'picture' => 'https://lh3.googleusercontent.com/foto',
            ]),
        ]);
    }

    /** Passa pelo /redirect para obter um state que o callback aceite. */
    private function stateValido(string $tipo = 'CLIENTE'): string
    {
        $location = (string) $this->get('/api/v1/auth/google/redirect?tipo='.$tipo)
            ->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return (string) $query['state'];
    }

    private function codigoDoRedirect(?string $location): string
    {
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        return (string) ($query['codigo'] ?? '');
    }
}
