<?php

namespace App\Proposals\Actions;

use App\Auth\Models\Usuario;
use App\Payments\CreatePaymentAuthorization;
use App\Payments\MetodoPagamento;
use App\Proposals\Events\ProposalAccepted;
use App\Proposals\Exceptions\ProposalException;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AcceptProposal
{
    public function __construct(
        private readonly CreatePaymentAuthorization $createAuthorization,
    ) {}

    /**
     * Aceita a proposta, recusa as demais (INV-011) e cria o Serviço (INV-020/021).
     * Transação + lock da solicitação + índice parcial UNIQUE(solicitacao_id)
     * WHERE status='ACEITA' garantem INV-010 sob concorrência.
     *
     * A autorização de pagamento (INV-C1) é criada na mesma transação do
     * aceite: se o gateway recusar, o aceite inteiro é desfeito (não existe
     * serviço sem autorização correspondente).
     *
     * @return array{proposta: Proposta, servico: Servico}
     */
    public function __invoke(
        Proposta $proposta,
        Usuario $cliente,
        MetodoPagamento $metodoPagamento,
        ?string $creditCardToken = null,
    ): array {
        try {
            return DB::transaction(function () use ($proposta, $cliente, $metodoPagamento, $creditCardToken): array {
                $solicitacao = Solicitacao::query()
                    ->whereKey($proposta->solicitacao_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                Proposta::query()
                    ->where('solicitacao_id', $solicitacao->id)
                    ->lockForUpdate()
                    ->get();

                $proposta->refresh();

                if ($solicitacao->cliente_id !== $cliente->id) {
                    throw ProposalException::forbidden(
                        'Apenas o cliente dono da solicitação pode aceitar esta proposta.',
                    );
                }

                if (in_array($solicitacao->status, [StatusSolicitacao::Cancelada, StatusSolicitacao::Expirada], true)) {
                    throw ProposalException::conflict(
                        'Não é possível aceitar proposta em solicitação cancelada ou expirada.',
                    );
                }

                if ($proposta->status === StatusProposta::Aceita) {
                    $servico = $proposta->servico;
                    if ($servico === null) {
                        throw ProposalException::conflict('Proposta aceita sem serviço associado.');
                    }

                    return [
                        'proposta' => $proposta->load('profissional.perfilProfissional'),
                        'servico' => $servico,
                    ];
                }

                if ($proposta->status !== StatusProposta::Enviada) {
                    throw ProposalException::conflict(
                        'Somente propostas enviadas podem ser aceitas.',
                    );
                }

                if (! $solicitacao->status->aceitaPropostas()) {
                    throw ProposalException::conflict(
                        'A solicitação não aceita propostas neste estado.',
                    );
                }

                $proposta->status = StatusProposta::Aceita;
                $proposta->save();

                Proposta::query()
                    ->where('solicitacao_id', $solicitacao->id)
                    ->where('id', '!=', $proposta->id)
                    ->where('status', StatusProposta::Enviada)
                    ->update(['status' => StatusProposta::Recusada->value]);

                $servico = Servico::query()->create([
                    'proposta_id' => $proposta->id,
                    'status' => StatusServico::Agendado,
                    'inicio' => null,
                    'fim' => null,
                ]);
                $servico->setRelation('proposta', $proposta);

                ($this->createAuthorization)($servico, $metodoPagamento, (string) $cliente->id, $creditCardToken);

                $solicitacao->status = StatusSolicitacao::Contratada;
                $solicitacao->save();

                $proposta->setRelation('servico', $servico);

                ProposalAccepted::dispatch($proposta, $servico);

                return [
                    'proposta' => $proposta->load('profissional.perfilProfissional'),
                    'servico' => $servico,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw ProposalException::conflict(
                'Já existe uma proposta aceita para esta solicitação.',
            );
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'propostas_solicitacao_aceita_unique')) {
                throw $exception;
            }

            throw ProposalException::conflict(
                'Já existe uma proposta aceita para esta solicitação.',
            );
        }
    }
}
