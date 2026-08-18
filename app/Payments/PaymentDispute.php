<?php

namespace App\Payments;

use App\Services\Servico;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDispute extends Model
{
    use HasUuids;

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

    protected function casts(): array
    {
        return [
            'tipo' => TipoPaymentDispute::class,
            'status' => StatusPaymentDispute::class,
            'aberta_em' => 'immutable_datetime',
            'resolvida_em' => 'immutable_datetime',
        ];
    }
}
