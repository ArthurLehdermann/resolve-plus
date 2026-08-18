<?php

namespace App\Professionals\Services;

use App\Auth\Enums\StatusConta;
use App\Auth\Models\Usuario;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Enums\TipoDocumentoProfissional;
use App\Professionals\Events\ProfissionalVerificado;
use App\Users\NivelConfianca;
use App\Users\PerfilProfissional;
use Illuminate\Support\Facades\DB;

final class ProfissionalVerificationService
{
    public function approve(DocumentoProfissional $documento, Usuario $admin): DocumentoProfissional
    {
        return DB::transaction(function () use ($documento, $admin): DocumentoProfissional {
            $documento->forceFill([
                'status' => StatusDocumentoProfissional::Aprovado,
                'motivo_rejeicao' => null,
                'revisado_por_id' => $admin->id,
                'revisado_em' => now(),
            ])->save();

            $profissional = $documento->profissional;

            if ($profissional !== null && $profissional->status === StatusConta::PendenteVerificacao) {
                $this->tryActivateProfissional($profissional);
            }

            return $documento->refresh();
        });
    }

    public function reject(DocumentoProfissional $documento, Usuario $admin, string $motivo): DocumentoProfissional
    {
        $documento->forceFill([
            'status' => StatusDocumentoProfissional::Rejeitado,
            'motivo_rejeicao' => $motivo,
            'revisado_por_id' => $admin->id,
            'revisado_em' => now(),
        ])->save();

        return $documento->refresh();
    }

    public function isSlotSatisfied(Usuario $profissional, TipoDocumentoProfissional $tipo): bool
    {
        $latest = DocumentoProfissional::query()
            ->where('profissional_id', $profissional->id)
            ->where('tipo', $tipo)
            ->orderByDesc('created_at')
            ->first();

        return $latest?->status === StatusDocumentoProfissional::Aprovado;
    }

    public function allRequiredSlotsSatisfied(Usuario $profissional): bool
    {
        $perfil = PerfilProfissional::query()
            ->where('usuario_id', $profissional->id)
            ->first();

        $categorias = $perfil?->categorias_atendidas ?? [];

        if ($categorias === []) {
            return false;
        }

        foreach (RequiredDocumentTypes::forCategorias($categorias) as $tipo) {
            if (! $this->isSlotSatisfied($profissional, $tipo)) {
                return false;
            }
        }

        return true;
    }

    private function tryActivateProfissional(Usuario $profissional): void
    {
        if (! $this->allRequiredSlotsSatisfied($profissional)) {
            return;
        }

        $profissional->forceFill([
            'status' => StatusConta::Ativa,
        ])->save();

        $perfil = PerfilProfissional::query()
            ->where('usuario_id', $profissional->id)
            ->first();

        $categorias = $perfil?->categorias_atendidas ?? [];

        PerfilProfissional::query()->updateOrCreate(
            ['usuario_id' => $profissional->id],
            [
                'categorias_atendidas' => $categorias,
                'nivel_confianca' => NivelConfianca::Verificado,
                'servicos_aprovados' => 0,
                'nota_media_dez' => null,
                'taxa_cancelamento_pct' => 0,
                'reclamacoes_12m' => 0,
                'nivel_atualizado_em' => now(),
            ]
        );

        ProfissionalVerificado::dispatch($profissional);
    }
}
