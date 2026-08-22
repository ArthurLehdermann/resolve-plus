<?php

namespace App\Auth\Models;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Professionals\DocumentoProfissional;
use App\Users\PerfilProfissional;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * E-mail é identificador, não texto livre: guarda sempre normalizado. O
     * teclado do celular capitaliza a primeira letra sozinho, e sem isso
     * "Fulano@x.com" vira uma conta diferente de "fulano@x.com" - ou, no login,
     * vira "credenciais inválidas" com a senha certa.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * Busca por e-mail. Usar sempre isto em vez de `where('email', ...)`: a
     * coluna guarda normalizado, então a entrada precisa passar pela mesma
     * normalização (e assim o índice de igualdade continua valendo).
     *
     * @param  Builder<Usuario>  $query
     * @return Builder<Usuario>
     */
    public function scopeComEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', mb_strtolower(trim($email)));
    }

    /**
     * @return HasOne<PerfilProfissional, $this>
     */
    public function perfilProfissional(): HasOne
    {
        return $this->hasOne(PerfilProfissional::class, 'usuario_id');
    }

    /**
     * @return HasMany<DocumentoProfissional, $this>
     */
    public function documentosProfissional(): HasMany
    {
        return $this->hasMany(DocumentoProfissional::class, 'profissional_id');
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
