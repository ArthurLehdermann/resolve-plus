<?php

namespace App\Services;

use App\Auth\Models\Usuario;
use App\Payments\PaymentDispute;
use App\Proposals\Proposta;
use App\Ratings\Avaliacao;
use App\Warranty\Garantia;
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
        'garantia_origem_id',
        'inicio',
        'fim',
        'notas',
        'fotos',
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
     * @return BelongsTo<Garantia, $this>
     */
    public function garantiaOrigem(): BelongsTo
    {
        return $this->belongsTo(Garantia::class, 'garantia_origem_id');
    }

    /**
     * @return HasOne<Garantia, $this>
     */
    public function garantia(): HasOne
    {
        return $this->hasOne(Garantia::class, 'servico_id');
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

    /**
     * @return HasMany<PaymentDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(PaymentDispute::class, 'servico_id');
    }

    /**
     * @return HasMany<Avaliacao, $this>
     */
    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class, 'servico_id');
    }

    public function isRevisitaGarantia(): bool
    {
        return $this->garantia_origem_id !== null;
    }

    public function profissionalId(): string
    {
        if ($this->isRevisitaGarantia()) {
            $this->loadMissing('garantiaOrigem.servico.proposta');

            return (string) $this->garantiaOrigem->servico->proposta->profissional_id;
        }

        $this->loadMissing('proposta');

        return (string) $this->proposta->profissional_id;
    }

    public function clienteId(): string
    {
        if ($this->isRevisitaGarantia()) {
            $this->loadMissing('garantiaOrigem.servico.proposta.solicitacao');

            return (string) $this->garantiaOrigem->servico->proposta->solicitacao->cliente_id;
        }

        $this->loadMissing('proposta.solicitacao');

        return (string) $this->proposta->solicitacao->cliente_id;
    }

    public function propertyId(): string
    {
        if ($this->isRevisitaGarantia()) {
            $this->loadMissing('garantiaOrigem.servico.proposta.solicitacao');

            return (string) $this->garantiaOrigem->servico->proposta->solicitacao->property_id;
        }

        $this->loadMissing('proposta.solicitacao');

        return (string) $this->proposta->solicitacao->property_id;
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

    public function isClienteDono(Usuario $usuario): bool
    {
        return (string) $usuario->id === $this->clienteId();
    }

    protected function casts(): array
    {
        return [
            'inicio' => 'immutable_datetime',
            'fim' => 'immutable_datetime',
            'fotos' => 'array',
            'status' => StatusServico::class,
        ];
    }

    protected static function newFactory(): ServicoFactory
    {
        return ServicoFactory::new();
    }
}
