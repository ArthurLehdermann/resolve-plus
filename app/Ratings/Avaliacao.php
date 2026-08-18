<?php

namespace App\Ratings;

use App\Auth\Models\Usuario;
use App\Services\Servico;
use Database\Factories\Ratings\AvaliacaoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avaliacao extends Model
{
    /** @use HasFactory<AvaliacaoFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $table = 'avaliacoes';

    protected $fillable = [
        'servico_id',
        'autor_id',
        'alvo_id',
        'direcao',
        'nota',
        'comentario',
    ];

    /**
     * @return BelongsTo<Servico, $this>
     */
    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function alvo(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'alvo_id');
    }

    protected function casts(): array
    {
        return [
            'direcao' => DirecaoAvaliacao::class,
            'nota' => 'integer',
        ];
    }

    protected static function newFactory(): AvaliacaoFactory
    {
        return AvaliacaoFactory::new();
    }
}
