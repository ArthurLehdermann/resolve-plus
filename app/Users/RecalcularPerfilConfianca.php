<?php

namespace App\Users;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use App\Ratings\Avaliacao;
use App\Ratings\DirecaoAvaliacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecalcularPerfilConfianca
{
    public function __construct(private readonly CalcularNivelConfianca $calcularNivel) {}

    public function __invoke(string $profissionalId): PerfilProfissional
    {
        return DB::transaction(function () use ($profissionalId): PerfilProfissional {
            $usuario = Usuario::query()
                ->whereKey($profissionalId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($usuario->tipo !== TipoUsuario::Profissional) {
                throw new InvalidArgumentException('Recálculo de confiança só se aplica a profissional.');
            }

            $notaMediaDez = $this->notaMediaDez($profissionalId);
            $servicosAprovados = $this->servicosAprovados($profissionalId);

            $perfil = PerfilProfissional::query()
                ->where('usuario_id', $profissionalId)
                ->lockForUpdate()
                ->first();

            if ($perfil === null) {
                $perfil = PerfilProfissional::query()->create([
                    'usuario_id' => $profissionalId,
                    'nivel_confianca' => NivelConfianca::Verificado,
                    'servicos_aprovados' => 0,
                    'nota_media_dez' => null,
                    'taxa_cancelamento_pct' => 0,
                    'reclamacoes_12m' => 0,
                    'nivel_atualizado_em' => now(),
                ]);
            }

            $perfil->servicos_aprovados = $servicosAprovados;
            $perfil->nota_media_dez = $notaMediaDez;

            $podePromover = $usuario->status === StatusConta::Ativa;
            if ($podePromover) {
                $diasConta = (int) abs($usuario->created_at?->diffInDays(now()) ?? 0);
                $novoNivel = ($this->calcularNivel)(
                    $servicosAprovados,
                    $notaMediaDez,
                    (int) $perfil->taxa_cancelamento_pct,
                    $diasConta,
                    (int) $perfil->reclamacoes_12m,
                );

                if ($novoNivel !== $perfil->nivel_confianca) {
                    $perfil->nivel_confianca = $novoNivel;
                    $perfil->nivel_atualizado_em = CarbonImmutable::now();
                }
            }

            $perfil->save();

            return $perfil->refresh();
        });
    }

    private function notaMediaDez(string $profissionalId): ?int
    {
        $media = Avaliacao::query()
            ->where('alvo_id', $profissionalId)
            ->where('direcao', DirecaoAvaliacao::ClienteAvaliaProfissional)
            ->avg('nota');

        if ($media === null) {
            return null;
        }

        return (int) round((float) $media * 10);
    }

    private function servicosAprovados(string $profissionalId): int
    {
        return Servico::query()
            ->where('status', StatusServico::Aprovado)
            ->whereIn(
                'proposta_id',
                Proposta::query()->select('id')->where('profissional_id', $profissionalId),
            )
            ->count();
    }
}
