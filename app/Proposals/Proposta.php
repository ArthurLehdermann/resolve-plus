<?php

namespace App\Proposals;

use App\Auth\Models\Usuario;
use App\Requests\Solicitacao;
use App\Services\Servico;
use Database\Factories\Proposals\PropostaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Proposta extends Model
{
    /** @use HasFactory<PropostaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'propostas';

    protected $fillable = [
        'solicitacao_id',
        'profissional_id',
        'valor',
        'prazo_dias',
        'garantia_dias',
        'observacoes',
        'status',
    ];

    /**
     * @return BelongsTo<Solicitacao, $this>
     */
    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'profissional_id');
    }

    /**
     * @return HasOne<Servico, $this>
     */
    public function servico(): HasOne
    {
        return $this->hasOne(Servico::class, 'proposta_id');
    }

    protected function casts(): array
    {
        return [
            'valor' => 'integer',
            'prazo_dias' => 'integer',
            'garantia_dias' => 'integer',
            'status' => StatusProposta::class,
        ];
    }

    protected static function newFactory(): PropostaFactory
    {
        return PropostaFactory::new();
    }
}
