<?php

namespace App\Requests;

use Database\Factories\Requests\FotoSolicitacaoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotoSolicitacao extends Model
{
    /** @use HasFactory<FotoSolicitacaoFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $table = 'fotos_solicitacao';

    protected $fillable = [
        'solicitacao_id',
        'url',
        'ordem',
    ];

    /**
     * @return BelongsTo<Solicitacao, $this>
     */
    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class);
    }

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    protected static function newFactory(): FotoSolicitacaoFactory
    {
        return FotoSolicitacaoFactory::new();
    }
}
