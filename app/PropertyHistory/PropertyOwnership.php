<?php

namespace App\PropertyHistory;

use App\Auth\Models\Usuario;
use Database\Factories\PropertyHistory\PropertyOwnershipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyOwnership extends Model
{
    /** @use HasFactory<PropertyOwnershipFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = [
        'property_id',
        'cliente_id',
        'desde',
        'ate',
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
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id');
    }

    protected function casts(): array
    {
        return [
            'desde' => 'immutable_datetime',
            'ate' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PropertyOwnershipFactory
    {
        return PropertyOwnershipFactory::new();
    }
}
