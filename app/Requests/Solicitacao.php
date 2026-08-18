<?php

namespace App\Requests;

use App\Auth\Models\Usuario;
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

    protected $table = 'solicitacoes';

    protected $fillable = [
        'cliente_id',
        'property_id',
        'status',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id');
    }

    /**
     * @return HasMany<Proposta, $this>
     */
    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class, 'solicitacao_id');
    }

    protected function casts(): array
    {
        return [
            'status' => StatusSolicitacao::class,
        ];
    }

    protected static function newFactory(): SolicitacaoFactory
    {
        return SolicitacaoFactory::new();
    }
}
