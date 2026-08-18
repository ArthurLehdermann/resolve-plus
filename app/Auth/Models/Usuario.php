<?php

namespace App\Auth\Models;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Users\PerfilProfissional;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'tipo',
        'nome',
        'email',
        'telefone',
        'senha_hash',
        'foto',
        'status',
    ];

    protected $hidden = [
        'senha_hash',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoUsuario::class,
            'status' => StatusConta::class,
            'senha_hash' => 'hashed',
        ];
    }

    /**
     * @return HasOne<PerfilProfissional, $this>
     */
    public function perfilProfissional(): HasOne
    {
        return $this->hasOne(PerfilProfissional::class, 'usuario_id');
    }

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }
}
