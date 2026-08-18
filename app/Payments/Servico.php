<?php

namespace App\Payments;

use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use Database\Factories\Payments\ServicoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servico extends Model
{
    /** @use HasFactory<ServicoFactory> */
    use HasFactory, HasUuids;

    protected $table = 'servicos';

    protected $fillable = [
        'proposta_id',
        'cliente_id',
        'profissional_id',
        'status',
        'asaas_wallet_id',
    ];

    /**
     * @return BelongsTo<Proposta, $this>
     */
    public function proposta(): BelongsTo
    {
        return $this->belongsTo(Proposta::class, 'proposta_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'profissional_id');
    }

    /**
     * @return HasMany<PaymentAuthorization, $this>
     */
    public function authorizations(): HasMany
    {
        return $this->hasMany(PaymentAuthorization::class, 'servico_id');
    }

    /**
     * @return HasMany<PaymentDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(PaymentDispute::class, 'servico_id');
    }

    protected function casts(): array
    {
        return [
            'status' => StatusServico::class,
        ];
    }

    protected static function newFactory(): ServicoFactory
    {
        return ServicoFactory::new();
    }
}
