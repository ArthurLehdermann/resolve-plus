<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Services\Events\ServiceFinished;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;

class FinishService
{
    /**
     * @param  list<string>  $photos
     */
    public function __invoke(Servico $servico, Usuario $usuario, ?string $notes, array $photos): Servico
    {
        $servico = DB::transaction(function () use ($servico, $usuario, $notes, $photos): Servico {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $servico->isProfissionalResponsavel($usuario)) {
                throw ServiceException::forbidden(
                    'Apenas o profissional da proposta aceita pode registrar a conclusão.',
                );
            }

            if ($servico->status !== StatusServico::EmAndamento) {
                throw ServiceException::conflict(
                    'Somente serviços em andamento podem ser concluídos.',
                );
            }

            $servico->status = StatusServico::AguardandoAprovacao;
            $servico->fim = now();
            $servico->notas = $notes;
            $servico->fotos = $photos;
            $servico->save();

            return $servico->refresh();
        });

        ServiceFinished::dispatch($servico);

        return $servico;
    }
}
