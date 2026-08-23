<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O app mostra ao usuário a mensagem que a API devolve, sem reescrever nada.
 * Com APP_LOCALE=pt_BR e nenhum arquivo em lang/, o Laravel devolve a chave
 * crua — foi assim que a tela de verificação chegou a exibir "validation.in"
 * para o profissional. Este teste é o alarme de que lang/pt_BR sumiu.
 */
class LocalizacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_come_translated_to_pt_br(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable();

        $mensagem = $response->json('errors.nome.0');

        $this->assertIsString($mensagem);
        $this->assertStringNotContainsString('validation.', $mensagem);
        $this->assertStringContainsString('obrigatório', $mensagem);
    }

    public function test_locale_is_pt_br(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
        $this->assertSame('pt_BR', config('app.fallback_locale'));
    }
}
