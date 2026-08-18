<?php

namespace App\Services;

use App\Proposals\Proposta;
use Database\Factories\Services\ServicoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
