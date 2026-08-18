<?php

namespace App\Payments;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PaymentRefund extends Model
{
    use HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_event_id',
        'valor',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'integer',
            'criado_em' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentRefund $refund): void {
            $event = PaymentEvent::query()->find($refund->payment_event_id);

            if ($event === null || $event->tipo !== TipoPaymentEvent::Capturado) {
                throw new LogicException('PaymentRefund só existe sobre evento CAPTURADO (INV-043).');
            }
        });
    }
}
