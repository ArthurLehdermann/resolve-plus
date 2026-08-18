<?php

namespace App\PropertyHistory;

use App\Auth\Models\Usuario;
use Database\Factories\PropertyHistory\PropertyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = [
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'latitude',
        'longitude',
        'apelido',
    ];

    /**
     * @return HasMany<PropertyOwnership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(PropertyOwnership::class);
    }

    /**
     * @return HasOne<PropertyOwnership, $this>
     */
    public function currentOwnership(): HasOne
    {
        return $this->hasOne(PropertyOwnership::class)->whereNull('ate');
    }

    public function isCurrentOwner(Usuario $usuario): bool
    {
        return $this->currentOwnership?->cliente_id === $usuario->id;
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Property $property): void {
            $property->chave_endereco = ChaveEndereco::from(
                (string) $property->cep,
                (string) $property->numero,
                $property->complemento,
            );
        });
    }

    protected static function newFactory(): PropertyFactory
    {
        return PropertyFactory::new();
    }
}
