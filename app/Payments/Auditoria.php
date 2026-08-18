<?php

namespace App\Payments;

use App\Auth\Models\Usuario;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Auditoria extends Model
{
    use HasUuids;

    public const CREATED_AT = 'data';

    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    protected $fillable = [
        'usuario_id',
        'acao',
        'entidade',
        'id_entidade',
        'ip',
        'justificativa',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Auditoria é append-only (INV-071): UPDATE é proibido.');
        });

        static::deleting(function (): never {
            throw new LogicException('Auditoria é append-only (INV-071): DELETE é proibido.');
        });
    }
}
