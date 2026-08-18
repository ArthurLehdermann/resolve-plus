<?php

namespace App\Payments;

use App\Services\Servico;
use Database\Factories\Payments\PaymentAuthorizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAuthorization extends Model
{
    /** @use HasFactory<PaymentAuthorizationFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'servico_id',
        'valor',
        'metodo',
        'status',
        'gateway_payment_id',
        'credit_card_token',
        'gateway_customer_id',
        'wallet_id',
        'expira_em',
    ];

    /**
     * @return BelongsTo<Servico, $this>
     */
    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('criado_em');
    }

    public function captureEvent(): ?PaymentEvent
    {
        return $this->events()
            ->where('tipo', TipoPaymentEvent::Capturado)
            ->latest('criado_em')
            ->first();
    }

    public function hasEvent(TipoPaymentEvent $tipo): bool
    {
        return $this->events()->where('tipo', $tipo)->exists();
    }

    public function hasRepasse(): bool
    {
        return $this->hasEvent(TipoPaymentEvent::Repassado);
    }

    /**
     * @param  Builder<PaymentAuthorization>  $query
     * @return Builder<PaymentAuthorization>
     */
    public function scopeAutorizado(Builder $query): Builder
    {
        return $query->where('status', StatusPaymentAuthorization::Autorizado);
    }

    protected function casts(): array
    {
        return [
            'valor' => 'integer',
            'metodo' => MetodoPagamento::class,
            'status' => StatusPaymentAuthorization::class,
            'credit_card_token' => 'encrypted',
            'expira_em' => 'immutable_datetime',
            'criado_em' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PaymentAuthorizationFactory
    {
        return PaymentAuthorizationFactory::new();
    }
}
