<?php

namespace App\Payments;

class CommissionRate
{
    public function current(): float
    {
        $row = Configuracao::query()->find('COMISSAO_PERCENT');

        if ($row === null) {
            return (float) config('payments.default_commission_percent');
        }

        return (float) $row->valor;
    }

    public function set(float $percent): void
    {
        Configuracao::query()->updateOrCreate(
            ['chave' => 'COMISSAO_PERCENT'],
            ['valor' => (string) $percent, 'atualizado_em' => now()],
        );
    }
}
