<?php

namespace App\Services;

use Database\Factories\Services\AgendaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agenda extends Model
{
    /** @use HasFactory<AgendaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'agendas';

    protected $fillable = [
        'servico_id',
        'data',
        'hora',
        'observacoes',
    ];

    /**
     * @return BelongsTo<Servico, $this>
     */
    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'date:Y-m-d',
        ];
    }

    protected static function newFactory(): AgendaFactory
    {
        return AgendaFactory::new();
    }
}
