<?php

namespace App\Payments;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'chave',
        'escopo',
        'response_status',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'criado_em' => 'immutable_datetime',
        ];
    }
}
