<?php

namespace App\Payments;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSplit extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'payment_event_id',
        'valor_profissional',
        'valor_plataforma',
        'aliquota_vigente',
    ];

    /**
     * @return BelongsTo<PaymentEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(PaymentEvent::class, 'payment_event_id');
    }

    protected function casts(): array
    {
        return [
            'valor_profissional' => 'integer',
            'valor_plataforma' => 'integer',
            'aliquota_vigente' => 'float',
        ];
    }
}
