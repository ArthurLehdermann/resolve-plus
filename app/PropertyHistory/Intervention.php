<?php

namespace App\PropertyHistory;

use Database\Factories\PropertyHistory\InterventionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Intervention extends Model
{
    /** @use HasFactory<InterventionFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $fillable = [
        'asset_id',
        'servico_id',
        'data',
        'categoria',
        'resumo',
        'origem',
    ];

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'immutable_datetime',
            'origem' => OrigemIntervention::class,
            'confiabilidade' => ConfiabilidadeIntervention::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Intervention $intervention): void {
            $origem = $intervention->origem instanceof OrigemIntervention
                ? $intervention->origem
                : OrigemIntervention::from((string) $intervention->origem);

            $intervention->setAttribute(
                'confiabilidade',
                ConfiabilidadeIntervention::fromOrigem($origem),
            );

            if ($origem !== OrigemIntervention::Plataforma) {
                $intervention->servico_id = null;
            }
        });
    }

    protected static function newFactory(): InterventionFactory
    {
        return InterventionFactory::new();
    }
}
