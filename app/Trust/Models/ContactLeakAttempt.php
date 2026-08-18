<?php

namespace App\Trust\Models;

use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use App\Services\Servico;
use App\Trust\Enums\OrigemVazamentoContato;
use App\Trust\Enums\PadraoContatoDetectado;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactLeakAttempt extends Model
{
    use HasUuids;

    protected $table = 'contact_leak_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origem' => OrigemVazamentoContato::class,
            'padrao_detectado' => PadraoContatoDetectado::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function proposta(): BelongsTo
    {
        return $this->belongsTo(Proposta::class, 'proposta_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }
}
