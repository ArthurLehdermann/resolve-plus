<?php

namespace App\Proposals\Actions;

use App\Auth\Models\Usuario;
use App\Proposals\Exceptions\ProposalException;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;

class WithdrawProposal
{
    public function __invoke(Proposta $proposta, Usuario $profissional): Proposta
    {
        if ($proposta->profissional_id !== $profissional->id) {
            throw ProposalException::forbidden(
                'Apenas o profissional autor pode retirar esta proposta.',
            );
        }

        if ($proposta->status !== StatusProposta::Enviada) {
            throw ProposalException::conflict(
                'Somente propostas enviadas podem ser retiradas.',
            );
        }

        $proposta->status = StatusProposta::Retirada;
        $proposta->save();

        return $proposta->load('profissional.perfilProfissional');
    }
}
