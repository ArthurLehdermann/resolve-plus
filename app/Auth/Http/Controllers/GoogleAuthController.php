<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Http\Resources\UsuarioResource;
use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login com Google pelo fluxo de código de autorização, com a troca feita aqui
 * no servidor: o client_secret nunca chega ao app, e o mesmo endpoint serve
 * web e mobile (o app só abre uma URL).
 *
 * O caminho é: app abre /auth/google/redirect -> Google -> /auth/google/callback
 * -> redireciona para o app com um código de uso único -> app troca esse código
 * pelo token de sessão em /auth/google/exchange. O token do Sanctum não passa
 * pela barra de endereço em nenhum momento (ficaria no histórico do navegador
 * e nos logs de acesso).
 */
class GoogleAuthController extends Controller
{
    /** Janela curta: o app troca o código no primeiro frame depois do redirect. */
    private const EXCHANGE_TTL_SECONDS = 120;

    private const STATE_TTL_SECONDS = 600;

    public function redirect(Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->configurado()) {
            // Navegação de página inteira não pode terminar em JSON cru na
            // cara do usuário: quem veio pelo botão volta para o app com erro.
            return $request->expectsJson()
                ? ApiResponse::error('Login com Google não está configurado nesta instalação.', 503)
                : $this->voltaParaOApp(['erro' => 'google_indisponivel']);
        }

        $tipo = $this->tipoSolicitado($request);
        $state = Str::random(40);

        Cache::put($this->stateKey($state), $tipo->value, self::STATE_TTL_SECONDS);

        $query = http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => (string) config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            // Sem refresh token: a sessão do app é o token do Sanctum, o Google
            // só responde "quem é" uma vez.
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configurado()) {
            return $this->voltaParaOApp(['erro' => 'google_indisponivel']);
        }

        $state = (string) $request->query('state', '');
        $tipoDoState = $state === '' ? null : Cache::pull($this->stateKey($state));

        // State ausente/desconhecido é requisição forjada ou link velho: não dá
        // para saber quem começou o fluxo, então não vira sessão.
        if (! is_string($tipoDoState)) {
            return $this->voltaParaOApp(['erro' => 'state_invalido']);
        }

        if ($request->query('error') !== null || $request->query('code') === null) {
            return $this->voltaParaOApp(['erro' => 'cancelado']);
        }

        $perfil = $this->perfilDoGoogle((string) $request->query('code'));

        if ($perfil === null) {
            return $this->voltaParaOApp(['erro' => 'falha_google']);
        }

        $usuario = $this->usuarioPara($perfil, TipoUsuario::from($tipoDoState));

        if ($usuario->status === StatusConta::Suspensa) {
            return $this->voltaParaOApp(['erro' => 'conta_suspensa']);
        }

        $codigo = Str::random(48);
        Cache::put($this->exchangeKey($codigo), $usuario->id, self::EXCHANGE_TTL_SECONDS);

        return $this->voltaParaOApp(['codigo' => $codigo]);
    }

    /**
     * Segunda perna do login: o app troca o código de uso único pelo token de
     * sessão. Consome o código na leitura, então replay não vale.
     */
    public function exchange(Request $request): JsonResponse
    {
        $codigo = $request->string('codigo')->toString();
        $usuarioId = $codigo === '' ? null : Cache::pull($this->exchangeKey($codigo));

        $usuario = is_string($usuarioId) ? Usuario::query()->find($usuarioId) : null;

        if ($usuario === null) {
            throw ValidationException::withMessages([
                'codigo' => ['Código de login inválido ou expirado.'],
            ]);
        }

        return ApiResponse::success([
            'user' => new UsuarioResource($usuario),
            'token' => $usuario->createToken('auth')->plainTextToken,
        ]);
    }

    /**
     * @return array{email: string, nome: string, foto: string|null}|null
     */
    private function perfilDoGoogle(string $code): ?array
    {
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'redirect_uri' => (string) config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if ($token->failed()) {
            return null;
        }

        $accessToken = $token->json('access_token');

        if (! is_string($accessToken)) {
            return null;
        }

        $perfil = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if ($perfil->failed()) {
            return null;
        }

        $email = $perfil->json('email');

        // E-mail não verificado no Google permitiria assumir a conta de outra
        // pessoa só cadastrando o endereço dela lá.
        if (! is_string($email) || $perfil->json('email_verified') !== true) {
            return null;
        }

        $nome = $perfil->json('name');
        $foto = $perfil->json('picture');

        return [
            'email' => $email,
            'nome' => is_string($nome) && $nome !== '' ? $nome : Str::before($email, '@'),
            'foto' => is_string($foto) ? $foto : null,
        ];
    }

    /**
     * @param  array{email: string, nome: string, foto: string|null}  $perfil
     */
    private function usuarioPara(array $perfil, TipoUsuario $tipo): Usuario
    {
        $usuario = Usuario::query()->comEmail($perfil['email'])->first();

        if ($usuario !== null) {
            // Conta existente entra como está: o tipo pedido no botão não
            // reclassifica quem já é cliente ou profissional.
            if ($usuario->foto === null && $perfil['foto'] !== null) {
                $usuario->foto = $perfil['foto'];
                $usuario->save();
            }

            return $usuario;
        }

        return Usuario::query()->create([
            'tipo' => $tipo,
            'nome' => $perfil['nome'],
            'email' => $perfil['email'],
            // Telefone é obrigatório no cadastro por formulário, mas o Google
            // não devolve: o perfil completa depois, em /users/me.
            'telefone' => '',
            'senha_hash' => null,
            'foto' => $perfil['foto'],
            'status' => $tipo === TipoUsuario::Cliente
                ? StatusConta::Ativa
                : StatusConta::PendenteVerificacao,
        ]);
    }

    private function tipoSolicitado(Request $request): TipoUsuario
    {
        $tipo = TipoUsuario::tryFrom(mb_strtoupper((string) $request->query('tipo', '')));

        return $tipo === TipoUsuario::Profissional
            ? TipoUsuario::Profissional
            : TipoUsuario::Cliente;
    }

    /**
     * @param  array<string, string>  $parametros
     */
    private function voltaParaOApp(array $parametros): RedirectResponse
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($base.'/auth/google?'.http_build_query($parametros));
    }

    private function configurado(): bool
    {
        return is_string(config('services.google.client_id'))
            && config('services.google.client_id') !== ''
            && is_string(config('services.google.client_secret'))
            && config('services.google.client_secret') !== '';
    }

    private function stateKey(string $state): string
    {
        return 'google-oauth:state:'.hash('sha256', $state);
    }

    private function exchangeKey(string $codigo): string
    {
        return 'google-oauth:exchange:'.hash('sha256', $codigo);
    }
}
