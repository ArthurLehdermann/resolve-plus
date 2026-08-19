<?php

namespace App\Services;

use App\Auth\Models\Usuario;
use Database\Factories\Services\MensagemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensagem extends Model
{
    /** @use HasFactory<MensagemFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'enviado_em';

    public const UPDATED_AT = null;

    protected $table = 'mensagens';

    public ?string $contactLeakWarning = null;

    protected $fillable = [
        'servico_id',
        'remetente_id',
        'texto',
        'texto_original',
        'anexo',
        'enviado_em',
    ];

    /**
     * @return BelongsTo<Servico, $this>
     */
    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function remetente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'remetente_id');
    }

    protected function casts(): array
    {
        return [
            'enviado_em' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): MensagemFactory
    {
        return MensagemFactory::new();
    }
}
