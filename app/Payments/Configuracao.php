<?php

namespace App\Payments;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'configuracoes';

    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    protected $fillable = [
        'chave',
        'valor',
        'atualizado_em',
    ];

    protected function casts(): array
    {
        return [
            'atualizado_em' => 'immutable_datetime',
        ];
    }
}
