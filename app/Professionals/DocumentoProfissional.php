<?php

namespace App\Professionals;

use App\Auth\Models\Usuario;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Enums\TipoDocumentoProfissional;
use Database\Factories\DocumentoProfissionalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoProfissional extends Model
{
    /** @use HasFactory<DocumentoProfissionalFactory> */
    use HasFactory, HasUuids;

    protected $table = 'documentos_profissional';

    protected $fillable = [
        'profissional_id',
        'tipo',
        'arquivo',
        'status',
        'motivo_rejeicao',
        'revisado_por_id',
        'revisado_em',
        'apolice_numero',
        'vigencia_inicio',
        'vigencia_fim',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoProfissional::class,
            'status' => StatusDocumentoProfissional::class,
            'revisado_em' => 'datetime',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'profissional_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por_id');
    }

    protected static function newFactory(): DocumentoProfissionalFactory
    {
        return DocumentoProfissionalFactory::new();
    }
}
