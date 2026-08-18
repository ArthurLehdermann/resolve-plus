<?php

namespace App\Payments;

use App\Auth\Models\Usuario;
use App\Services\Servico;
use Database\Factories\Payments\PaymentDisputeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDispute extends Model
{
    /** @use HasFactory<PaymentDisputeFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'aberta_em';

    public const UPDATED_AT = null;

    protected $table = 'payment_disputes';

    protected $fillable = [
        'servico_id',
        'tipo',
        'status',
        'motivo',
        'aberta_em',
        'resolvida_em',
        'resolvida_por_id',
        'resultado',
        'justificativa',
    ];

    /**
     * @return BelongsTo<Servico, $this>
     */
    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function resolvidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resolvida_por_id');
    }

    public function isOpen(): bool
    {
        return $this->status === StatusPaymentDispute::Aberta;
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoPaymentDispute::class,
            'status' => StatusPaymentDispute::class,
            'resultado' => ResultadoPaymentDispute::class,
            'aberta_em' => 'immutable_datetime',
            'resolvida_em' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PaymentDisputeFactory
    {
        return PaymentDisputeFactory::new();
    }
}
