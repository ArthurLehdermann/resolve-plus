<?php

namespace App\Ratings\Actions;

use App\Auth\Models\Usuario;
use App\Ratings\Avaliacao;
use App\Ratings\DirecaoAvaliacao;
use App\Ratings\Events\AvaliacaoRegistrada;
use App\Ratings\Exceptions\RatingException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RegisterRating
{
    public function __invoke(Servico $servico, Usuario $autor, int $nota, ?string $comentario): Avaliacao
    {
        $avaliacao = DB::transaction(function () use ($servico, $autor, $nota, $comentario): Avaliacao {
            $servico = Servico::query()
                ->with('proposta.solicitacao')
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($servico->status !== StatusServico::Aprovado) {
                throw RatingException::conflict(
                    'Avaliação só é permitida com o serviço em APROVADO (RN004).',
                );
            }

            [$direcao, $alvoId] = $this->direcaoEAlvo($servico, $autor);

            $jaExiste = Avaliacao::query()
                ->where('servico_id', $servico->id)
                ->where('direcao', $direcao)
                ->exists();

            if ($jaExiste) {
                throw RatingException::conflict(
                    'Já existe uma avaliação nesta direção para o serviço.',
                );
            }

            try {
                return Avaliacao::query()->create([
                    'servico_id' => $servico->id,
                    'autor_id' => $autor->id,
                    'alvo_id' => $alvoId,
                    'direcao' => $direcao,
                    'nota' => $nota,
                    'comentario' => $comentario,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw RatingException::conflict(
                    'Já existe uma avaliação nesta direção para o serviço.',
                );
            }
        });

        AvaliacaoRegistrada::dispatch($avaliacao);

        return $avaliacao;
    }

    /**
     * @return array{0: DirecaoAvaliacao, 1: string}
     */
    private function direcaoEAlvo(Servico $servico, Usuario $autor): array
    {
        if ($servico->isClienteDono($autor)) {
            return [DirecaoAvaliacao::ClienteAvaliaProfissional, $servico->profissionalId()];
        }

        if ($servico->isProfissionalResponsavel($autor)) {
            return [DirecaoAvaliacao::ProfissionalAvaliaCliente, $servico->clienteId()];
        }

        throw RatingException::forbidden('Apenas as partes do serviço podem avaliar.');
    }
}
