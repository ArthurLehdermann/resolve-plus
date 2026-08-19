<?php

namespace App\Users;

use App\Auth\Models\Usuario;
use Database\Factories\Users\PerfilProfissionalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilProfissional extends Model
{
    /** @use HasFactory<PerfilProfissionalFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $table = 'perfis_profissionais';

    protected $fillable = [
        'usuario_id',
        'categorias_atendidas',
        'nivel_confianca',
        'servicos_aprovados',
        'nota_media_dez',
        'taxa_cancelamento_pct',
        'reclamacoes_12m',
        'nivel_atualizado_em',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function notaMedia(): ?float
    {
        if ($this->nota_media_dez === null) {
            return null;
        }

        return $this->nota_media_dez / 10;
    }

    protected function casts(): array
    {
        return [
            'categorias_atendidas' => 'array',
            'nivel_confianca' => NivelConfianca::class,
            'servicos_aprovados' => 'integer',
            'nota_media_dez' => 'integer',
            'taxa_cancelamento_pct' => 'integer',
            'reclamacoes_12m' => 'integer',
            'nivel_atualizado_em' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PerfilProfissionalFactory
    {
        return PerfilProfissionalFactory::new();
    }
}
