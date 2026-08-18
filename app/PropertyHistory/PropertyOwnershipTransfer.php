<?php

namespace App\PropertyHistory;

use App\Auth\Models\Usuario;
use Database\Factories\PropertyHistory\PropertyOwnershipTransferFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyOwnershipTransfer extends Model
{
    /** @use HasFactory<PropertyOwnershipTransferFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    public const EXPIRATION_DAYS = 7;

    protected $fillable = [
        'property_id',
        'de_cliente_id',
        'para_cliente_id',
        'para_email',
        'status',
        'expira_em',
    ];

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function deCliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'de_cliente_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function paraCliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'para_cliente_id');
    }

    public function isDestination(Usuario $usuario): bool
    {
        if ($this->para_cliente_id !== null) {
            return $this->para_cliente_id === $usuario->id;
        }

        return strcasecmp((string) $this->para_email, $usuario->email) === 0;
    }

    public function isExpired(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }

    protected function casts(): array
    {
        return [
            'status' => StatusPropertyOwnershipTransfer::class,
            'expira_em' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PropertyOwnershipTransferFactory
    {
        return PropertyOwnershipTransferFactory::new();
    }
}
