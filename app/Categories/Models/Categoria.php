<?php

namespace App\Categories\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'categorias';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'ativo',
        'template_escopo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'template_escopo' => 'array',
        ];
    }

    /**
     * @param  Builder<Categoria>  $query
     * @return Builder<Categoria>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    protected static function newFactory(): CategoriaFactory
    {
        return CategoriaFactory::new();
    }
}
