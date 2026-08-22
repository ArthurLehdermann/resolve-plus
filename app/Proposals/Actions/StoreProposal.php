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
use App\Trust\ContactLeakEnforcer;
use App\Trust\Enums\OrigemVazamentoContato;
use Illuminate\Support\Facades\DB;

class StoreProposal
{
    public function __construct(private readonly ContactLeakEnforcer $enforcer) {}

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

            $notes = $payload['notes'] ?? null;

            $proposta = Proposta::query()->create([
                'solicitacao_id' => $solicitacao->id,
                'profissional_id' => $profissional->id,
                'valor' => $payload['price'],
                'prazo_dias' => $payload['deadline_days'],
                'garantia_dias' => $payload['warranty_days'],
                'observacoes' => $notes,
                'status' => StatusProposta::Enviada,
            ]);

            if (is_string($notes) && $notes !== '') {
                $enforcement = $this->enforcer->apply(
                    usuario: $profissional,
                    origem: OrigemVazamentoContato::Proposta,
                    text: $notes,
                    propostaId: $proposta->id,
                );

                if ($enforcement['changed']) {
                    $proposta->forceFill([
                        'observacoes' => $enforcement['sanitized'],
                        'observacoes_original' => $notes,
                    ])->save();
                }

                $proposta->contactLeakWarning = $enforcement['warning'];
            }

            if ($solicitacao->status === StatusSolicitacao::Aberta) {
                $solicitacao->status = StatusSolicitacao::RecebendoPropostas;
                $solicitacao->save();
            }

            ProposalCreated::dispatch($proposta);

            return $proposta->load('profissional.perfilProfissional');
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
