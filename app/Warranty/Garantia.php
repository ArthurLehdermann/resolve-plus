<?php

namespace App\Warranty;

use App\Services\Servico;
use Database\Factories\Warranty\GarantiaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Garantia extends Model
{
    /** @use HasFactory<GarantiaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'garantias';

    protected $fillable = [
        'servico_id',
        'inicio',
        'fim',
        'status',
        'responsavel_financeiro',
    ];

    /**
     * @return BelongsTo<Servico, $this>
     */
    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    /**
     * @return HasMany<WarrantyClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class)->orderBy('criado_em');
    }

    protected function casts(): array
    {
        return [
            'inicio' => 'immutable_datetime',
            'fim' => 'immutable_datetime',
            'status' => StatusGarantia::class,
            'responsavel_financeiro' => ResponsavelFinanceiro::class,
        ];
    }

    protected static function newFactory(): GarantiaFactory
    {
        return GarantiaFactory::new();
    }
}
