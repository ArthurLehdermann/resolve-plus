<?php

namespace App\Payments;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class PaymentEvent extends Model
{
    use HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_authorization_id',
        'tipo',
        'payload',
    ];

    /**
     * @return BelongsTo<PaymentAuthorization, $this>
     */
    public function authorization(): BelongsTo
    {
        return $this->belongsTo(PaymentAuthorization::class, 'payment_authorization_id');
    }

    /**
     * @return HasOne<PaymentSplit, $this>
     */
    public function split(): HasOne
    {
        return $this->hasOne(PaymentSplit::class);
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoPaymentEvent::class,
            'payload' => 'array',
            'criado_em' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('PaymentEvent é append-only (INV-040): UPDATE é proibido.');
        });

        static::deleting(function (): never {
            throw new LogicException('PaymentEvent é append-only (INV-040): DELETE é proibido.');
        });
    }
}
