<?php

namespace App\Admin;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $primaryKey = 'chave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'chave',
        'valor',
    ];

    public static function inteiro(string $chave): int
    {
        $valor = static::query()->where('chave', $chave)->value('valor');

        if ($valor === null || ! is_numeric($valor)) {
            throw new RuntimeException("Configuração ausente ou inválida: {$chave}");
        }

        return (int) $valor;
    }
}
