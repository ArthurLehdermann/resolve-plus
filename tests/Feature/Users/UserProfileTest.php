<?php

namespace Tests\Feature\Users;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\Users\Jobs\ProcessUserAvatarJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_users_me_returns_authenticated_user(): void
    {
        $usuario = Usuario::factory()->create([
            'nome' => 'Maria Cliente',
            'email' => 'maria@example.com',
        ]);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $usuario->id)
            ->assertJsonPath('data.nome', 'Maria Cliente')
            ->assertJsonPath('data.email', 'maria@example.com')
            ->assertJsonPath('data.tipo', 'CLIENTE')
            ->assertJsonMissingPath('data.trust_profile');
    }

    public function test_get_users_me_includes_trust_profile_for_profissional(): void
    {
        $usuario = Usuario::factory()->profissional()->create();
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.tipo', 'PROFISSIONAL')
            ->assertJsonPath('data.trust_profile.servicos_aprovados', 0)
            ->assertJsonPath('data.trust_profile.taxa_cancelamento_pct', 0)
            ->assertJsonPath('data.trust_profile.reclamacoes_12m', 0)
            ->assertJsonStructure(['data' => ['trust_profile' => [
                'nivel_confianca',
                'servicos_aprovados',
                'nota_media',
                'taxa_cancelamento_pct',
                'reclamacoes_12m',
            ]]]);
    }

    public function test_put_users_me_updates_editable_profile_fields(): void
    {
        $usuario = Usuario::factory()->create([
            'nome' => 'Nome Antigo',
            'email' => 'antigo@example.com',
            'telefone' => '11900000000',
        ]);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/users/me', [
                'nome' => 'Nome Novo',
                'email' => 'novo@example.com',
                'telefone' => '11911112222',
                'tipo' => TipoUsuario::Admin->value,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nome', 'Nome Novo')
            ->assertJsonPath('data.email', 'novo@example.com')
            ->assertJsonPath('data.telefone', '11911112222')
            ->assertJsonPath('data.tipo', 'CLIENTE');

        $usuario->refresh();
        $this->assertSame('Nome Novo', $usuario->nome);
        $this->assertSame('novo@example.com', $usuario->email);
        $this->assertSame('11911112222', $usuario->telefone);
        $this->assertSame(TipoUsuario::Cliente, $usuario->tipo);
    }

    /**
     * A tela de verificação monta as opções com GET /categorias, então tudo
     * que estiver ativo no banco precisa ser salvável. Quando a validação
     * olhava só para o seed do MVP, o profissional marcava uma categoria que o
     * próprio app tinha acabado de listar e levava "inválido" na cara.
     */
    public function test_put_users_me_accepts_active_category_created_after_the_mvp_seed(): void
    {
        Categoria::factory()->create(['codigo' => 'telhado', 'ativo' => true]);

        $usuario = Usuario::factory()->create(['tipo' => TipoUsuario::Profissional]);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/users/me', [
                'categorias_atendidas' => ['telhado'],
            ])
            ->assertOk()
            ->assertJsonPath('data.categorias_atendidas', ['telhado']);
    }

    public function test_put_users_me_rejects_unknown_or_inactive_category(): void
    {
        Categoria::factory()->inativa()->create(['codigo' => 'desativada']);

        $usuario = Usuario::factory()->create(['tipo' => TipoUsuario::Profissional]);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/users/me', ['categorias_atendidas' => ['nao_existe']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categorias_atendidas.0']);

        $this->withToken($token)
            ->putJson('/api/v1/users/me', ['categorias_atendidas' => ['desativada']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categorias_atendidas.0']);
    }

    public function test_put_users_me_rejects_duplicate_email(): void
    {
        Usuario::factory()->create(['email' => 'ocupado@example.com']);
        $usuario = Usuario::factory()->create(['email' => 'livre@example.com']);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/users/me', [
                'email' => 'ocupado@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_post_users_photo_uploads_to_object_storage_and_dispatches_job(): void
    {
        Storage::fake((string) config('filesystems.object_disk', 's3'));
        Queue::fake();

        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('auth')->plainTextToken;
        $photo = UploadedFile::fake()->image('avatar.jpg', 400, 300);

        $response = $this->withToken($token)
            ->postJson('/api/v1/users/photo', [
                'photo' => $photo,
            ]);

        $response->assertAccepted()
            ->assertJsonPath('success', true);

        $usuario->refresh();
        $this->assertNotNull($usuario->foto);
        $this->assertStringStartsWith('avatars/'.$usuario->id.'/', $usuario->foto);

        Storage::disk((string) config('filesystems.object_disk', 's3'))
            ->assertExists($usuario->foto);

        Queue::assertPushed(ProcessUserAvatarJob::class, function (ProcessUserAvatarJob $job) use ($usuario): bool {
            return $job->usuarioId === $usuario->id
                && $job->originalPath === $usuario->foto;
        });
    }

    public function test_process_user_avatar_job_stores_thumbnail_and_updates_foto(): void
    {
        $disk = (string) config('filesystems.object_disk', 's3');
        Storage::fake($disk);

        $usuario = Usuario::factory()->create();
        $photo = UploadedFile::fake()->image('avatar.png', 400, 300);
        $originalPath = $photo->storeAs('avatars/'.$usuario->id, 'original.png', [
            'disk' => $disk,
        ]);

        $this->assertNotFalse($originalPath);

        $job = new ProcessUserAvatarJob($usuario->id, $originalPath);
        $job->handle();

        $usuario->refresh();
        $expectedThumb = 'avatars/'.$usuario->id.'/original_thumb.jpg';
        $this->assertSame($expectedThumb, $usuario->foto);
        Storage::disk($disk)->assertExists($expectedThumb);
    }

    public function test_users_me_and_photo_require_bearer_token(): void
    {
        $this->getJson('/api/v1/users/me')->assertUnauthorized();
        $this->putJson('/api/v1/users/me', ['nome' => 'X'])->assertUnauthorized();
        $this->postJson('/api/v1/users/photo')->assertUnauthorized();
    }

    public function test_post_users_photo_rejects_non_image(): void
    {
        Storage::fake((string) config('filesystems.object_disk', 's3'));

        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/users/photo', [
                'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }
}
