<?php

namespace App\Payments\Webhooks;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro bruto de cada webhook recebido do gateway, usado só pra
 * idempotência (gateway_event_id é unique) e auditoria/replay manual.
 * Não é a fonte de verdade do estado do pagamento - isso continua em
 * PaymentAuthorization/PaymentEvent.
 */
class PaymentWebhookEvent extends Model
{
    use HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'gateway_event_id',
        'event_type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'criado_em' => 'immutable_datetime',
        ];
    }
}
