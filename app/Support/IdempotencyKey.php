<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasUuids;

    protected $table = 'idempotency_keys';

    protected $fillable = [
        'usuario_id',
        'chave',
        'endpoint',
        'status_code',
        'response_body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'status_code' => 'integer',
        ];
    }
}
