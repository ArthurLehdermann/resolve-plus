<?php

namespace Tests\Feature\Professionals;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Enums\TipoDocumentoProfissional;
use App\Users\PerfilProfissional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoProfissionalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_profissional_can_upload_documento(): void
    {
        Storage::fake((string) config('filesystems.object_disk', 's3'));
        $profissional = Usuario::factory()->profissional()->create();
        $token = $profissional->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/professionals/documents', [
            'tipo' => TipoDocumentoProfissional::IdentidadeFiscal->value,
            'arquivo' => UploadedFile::fake()->create('cpf.pdf', 128, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', TipoDocumentoProfissional::IdentidadeFiscal->value)
            ->assertJsonPath('data.status', StatusDocumentoProfissional::Pendente->value);

        $documento = DocumentoProfissional::query()->firstOrFail();
        Storage::disk((string) config('filesystems.object_disk', 's3'))
            ->assertExists($documento->arquivo);
    }

    public function test_admin_review_approves_all_required_documents_and_activates_profissional(): void
    {
        $profissional = Usuario::factory()->profissional()->create([
            'status' => StatusConta::PendenteVerificacao,
        ]);
        PerfilProfissional::factory()->create([
            'usuario_id' => $profissional->id,
            'categorias_atendidas' => ['pintura'],
        ]);
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin,
            'status' => StatusConta::Ativa,
        ]);
        $adminToken = $admin->createToken('auth')->plainTextToken;

        foreach (TipoDocumentoProfissional::baseRequired() as $tipo) {
            DocumentoProfissional::factory()->create([
                'profissional_id' => $profissional->id,
                'tipo' => $tipo,
                'status' => StatusDocumentoProfissional::Pendente,
            ]);
        }

        $documentos = DocumentoProfissional::query()
            ->where('profissional_id', $profissional->id)
            ->orderBy('created_at')
            ->get();

        foreach ($documentos as $documento) {
            $this->withToken($adminToken)
                ->patchJson("/api/v1/admin/professionals/documents/{$documento->id}/review", [
                    'status' => StatusDocumentoProfissional::Aprovado->value,
                ])
                ->assertOk()
                ->assertJsonPath('data.status', StatusDocumentoProfissional::Aprovado->value);
        }

        $profissional->refresh();
        $this->assertSame(StatusConta::Ativa, $profissional->status);

        $perfil = PerfilProfissional::query()->where('usuario_id', $profissional->id)->first();
        $this->assertNotNull($perfil);
        $this->assertSame('VERIFICADO', $perfil->nivel_confianca?->value);
    }

    public function test_admin_rejection_keeps_profissional_pending_verification(): void
    {
        $profissional = Usuario::factory()->profissional()->create([
            'status' => StatusConta::PendenteVerificacao,
        ]);
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin,
            'status' => StatusConta::Ativa,
        ]);
        $documento = DocumentoProfissional::factory()->create([
            'profissional_id' => $profissional->id,
            'tipo' => TipoDocumentoProfissional::IdentidadeFiscal,
        ]);

        $this->withToken($admin->createToken('auth')->plainTextToken)
            ->patchJson("/api/v1/admin/professionals/documents/{$documento->id}/review", [
                'status' => StatusDocumentoProfissional::Rejeitado->value,
                'motivo_rejeicao' => 'Documento ilegível',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', StatusDocumentoProfissional::Rejeitado->value)
            ->assertJsonPath('data.motivo_rejeicao', 'Documento ilegível');

        $profissional->refresh();
        $this->assertSame(StatusConta::PendenteVerificacao, $profissional->status);
    }
}
