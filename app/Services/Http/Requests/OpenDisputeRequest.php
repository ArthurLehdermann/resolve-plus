<?php

namespace App\Services\Http\Requests;

use App\Auth\Models\Usuario;
use App\Payments\TipoPaymentDispute;
use App\Services\Servico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        if (! $usuario instanceof Usuario) {
            return false;
        }

        $id = $this->route('id');

        if (! is_string($id)) {
            return false;
        }

        $servico = Servico::query()->find($id);

        if ($servico === null) {
            return true;
        }

        return $servico->isParticipante($usuario);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Whitelist explícita, não TipoPaymentDispute::cases(): CHARGEBACK
            // é aberto só pelo webhook do Asaas (HandleAsaasWebhook), nunca
            // pelo usuário - senão qualquer parte trava captura/repasse
            // (INV-045) autodeclarando um chargeback que não aconteceu.
            'tipo' => ['required', 'string', Rule::in([
                TipoPaymentDispute::ContestacaoConclusao->value,
                TipoPaymentDispute::CancelamentoExecucao->value,
            ])],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function tipo(): TipoPaymentDispute
    {
        return TipoPaymentDispute::from($this->string('tipo')->toString());
    }

    public function motivo(): ?string
    {
        $motivo = $this->input('motivo');

        return is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null;
    }
}
