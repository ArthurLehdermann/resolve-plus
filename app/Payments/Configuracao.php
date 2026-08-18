<?php

namespace App\Payments;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    public $incrementing = false;

    protected $table = 'configuracoes';

    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    protected $fillable = [
        'chave',
        'valor',
    ];
}
