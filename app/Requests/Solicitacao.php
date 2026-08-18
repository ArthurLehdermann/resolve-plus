<?php

namespace App\Requests;

use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\PropertyHistory\Property;
use App\Proposals\Proposta;
use Database\Factories\Requests\SolicitacaoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Solicitacao extends Model
{
    /** @use HasFactory<SolicitacaoFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $table = 'solicitacoes';

    protected $fillable = [
        'cliente_id',
        'categoria_id',
        'property_id',
        'descricao',
        'escopo',
        'status',
        'data_desejada',
        'faixa_preco_min',
        'faixa_preco_max',
        'faixa_preco_fator_bp',
        'tabela_preco_id',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id');
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return HasMany<FotoSolicitacao, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoSolicitacao::class)->orderBy('ordem');
    }

    /**
     * @return HasMany<Proposta, $this>
     */
    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class, 'solicitacao_id');
    }

    public function ownedBy(Usuario $usuario): bool
    {
        return $this->cliente_id === $usuario->id;
    }

    public function hasPropostas(): bool
    {
        return $this->propostas()->exists();
    }

    protected function casts(): array
    {
        return [
            'escopo' => 'array',
            'status' => StatusSolicitacao::class,
            'data_desejada' => 'date',
            'faixa_preco_min' => 'integer',
            'faixa_preco_max' => 'integer',
            'faixa_preco_fator_bp' => 'integer',
        ];
    }

    protected static function newFactory(): SolicitacaoFactory
    {
        return SolicitacaoFactory::new();
    }
}
