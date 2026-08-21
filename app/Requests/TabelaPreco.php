<?php

namespace App\Requests;

use App\Categories\Models\Categoria;
use Database\Factories\Requests\TabelaPrecoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabelaPreco extends Model
{
    /** @use HasFactory<TabelaPrecoFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $table = 'tabelas_preco';

    protected $fillable = [
        'categoria_id',
        'cidade',
        'valor_min',
        'valor_max',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor_min' => 'integer',
            'valor_max' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    protected static function newFactory(): TabelaPrecoFactory
    {
        return TabelaPrecoFactory::new();
    }
}
