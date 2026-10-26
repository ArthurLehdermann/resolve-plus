<?php

namespace Tests\Feature\Auth;

use App\Auth\Mail\NovoUsuarioAdminMail;
use App\Auth\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AvisoNovoCadastroTest extends TestCase
{
    use RefreshDatabase;

    private function cadastrar(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'tipo' => 'CLIENTE',
            'nome' => 'Maria Teste',
            'email' => 'maria@example.com',
            'telefone' => '51999990000',
        ])->assertCreated();
    }

    public function test_cadastro_por_formulario_avisa_o_admin(): void
    {
        config()->set('mail.admin_address', 'dono@example.com');
        Mail::fake();

        $this->cadastrar();

        Mail::assertQueued(NovoUsuarioAdminMail::class, fn (NovoUsuarioAdminMail $mail): bool => $mail->hasTo('dono@example.com')
            && $mail->usuario->email === 'maria@example.com'
            && $mail->origem === 'formulário');
    }

    public function test_sem_admin_configurado_nao_avisa(): void
    {
        config()->set('mail.admin_address', null);
        Mail::fake();

        $this->cadastrar();

        Mail::assertNotQueued(NovoUsuarioAdminMail::class);
    }

    public function test_aviso_traz_nome_email_e_tipo(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Maria Teste', 'email' => 'maria@example.com']);

        $mail = new NovoUsuarioAdminMail($usuario, 'Google');
        $html = $mail->render();

        $this->assertStringContainsString('Maria Teste', $html);
        $this->assertStringContainsString('maria@example.com', $html);
        $this->assertStringContainsString('Google', $html);
        $this->assertStringContainsString('Novo cadastro no Resolve+: Maria Teste', $mail->envelope()->subject);
    }
}
