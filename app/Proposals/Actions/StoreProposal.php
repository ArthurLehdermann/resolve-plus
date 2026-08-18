<?php

namespace App\Proposals\Actions;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Proposals\Events\ProposalCreated;
use App\Proposals\Exceptions\ProposalException;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use Illuminate\Support\Facades\DB;

class StoreProposal
{
    /**
     * @param  array{price: int, deadline_days: int, warranty_days: int, notes?: string|null}  $payload
     */
    public function __invoke(Solicitacao $solicitacao, Usuario $profissional, array $payload): Proposta
    {
        $this->assertProfissionalAtivo($profissional);

        if (! $solicitacao->status->aceitaPropostas()) {
            throw ProposalException::conflict(
                'A solicitação não aceita novas propostas neste estado.',
            );
        }

        return DB::transaction(function () use ($solicitacao, $profissional, $payload): Proposta {
            $solicitacao = Solicitacao::query()
                ->whereKey($solicitacao->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $solicitacao->status->aceitaPropostas()) {
                throw ProposalException::conflict(
                    'A solicitação não aceita novas propostas neste estado.',
                );
            }

            $proposta = Proposta::query()->create([
                'solicitacao_id' => $solicitacao->id,
                'profissional_id' => $profissional->id,
                'valor' => $payload['price'],
                'prazo_dias' => $payload['deadline_days'],
                'garantia_dias' => $payload['warranty_days'],
                // TODO(W15 / antidesintermediação): mascarar Proposta.observacoes
                // conforme docs/specifications/09-mecanismo-antidesintermediacao.md §1
                // (telefone, e-mail, handles). Filtro ainda não implementado —
                // não bloqueia esta issue; reabrir após W15.
                'observacoes' => $payload['notes'] ?? null,
                'status' => StatusProposta::Enviada,
            ]);

            if ($solicitacao->status === StatusSolicitacao::Aberta) {
                $solicitacao->status = StatusSolicitacao::RecebendoPropostas;
                $solicitacao->save();
            }

            ProposalCreated::dispatch($proposta);

            return $proposta->load('profissional');
        });
    }

    private function assertProfissionalAtivo(Usuario $usuario): void
    {
        if ($usuario->tipo !== TipoUsuario::Profissional || $usuario->status !== StatusConta::Ativa) {
            throw ProposalException::forbidden(
                'Apenas profissionais com conta ativa podem enviar propostas.',
            );
        }
    }
}
