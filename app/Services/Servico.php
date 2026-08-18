<?php

namespace App\Services;

use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use Database\Factories\Services\ServicoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Servico extends Model
{
    /** @use HasFactory<ServicoFactory> */
    use HasFactory, HasUuids;

    protected $table = 'servicos';

    protected $fillable = [
        'proposta_id',
        'inicio',
        'fim',
        'status',
    ];

    /**
     * @return BelongsTo<Proposta, $this>
     */
    public function proposta(): BelongsTo
    {
        return $this->belongsTo(Proposta::class, 'proposta_id');
    }

    /**
     * @return HasOne<Agenda, $this>
     */
    public function agenda(): HasOne
    {
        return $this->hasOne(Agenda::class, 'servico_id');
    }

    /**
     * @return HasMany<Mensagem, $this>
     */
    public function mensagens(): HasMany
    {
        return $this->hasMany(Mensagem::class, 'servico_id');
    }

    public function profissionalId(): string
    {
        $this->loadMissing('proposta');

        return (string) $this->proposta->profissional_id;
    }

    public function clienteId(): string
    {
        $this->loadMissing('proposta.solicitacao');

        return (string) $this->proposta->solicitacao->cliente_id;
    }

    public function isParticipante(Usuario $usuario): bool
    {
        $id = (string) $usuario->id;

        return $id === $this->clienteId() || $id === $this->profissionalId();
    }

    public function isProfissionalResponsavel(Usuario $usuario): bool
    {
        return (string) $usuario->id === $this->profissionalId();
    }

    protected function casts(): array
    {
        return [
            'inicio' => 'immutable_datetime',
            'fim' => 'immutable_datetime',
            'status' => StatusServico::class,
        ];
    }

    protected static function newFactory(): ServicoFactory
    {
        return ServicoFactory::new();
    }
}
