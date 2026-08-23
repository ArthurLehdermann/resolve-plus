<?php

namespace App\Notifications;

use App\Auth\Models\Usuario;
use Database\Factories\Notifications\NotificacaoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacao extends Model
{
    /** @use HasFactory<NotificacaoFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    protected $table = 'notificacoes';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'titulo',
        'corpo',
        'dados',
        'lida_em',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function isLida(): bool
    {
        return $this->lida_em !== null;
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoNotificacao::class,
            'dados' => 'array',
            'lida_em' => 'immutable_datetime',
        ];
    }
}
