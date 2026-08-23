<?php

namespace Database\Factories\Notifications;

use App\Auth\Models\Usuario;
use App\Notifications\Notificacao;
use App\Notifications\TipoNotificacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notificacao>
 */
class NotificacaoFactory extends Factory
{
    protected $model = Notificacao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'tipo' => TipoNotificacao::SolicitacaoNova,
            'titulo' => fake()->sentence(4),
            'corpo' => fake()->sentence(12),
            'dados' => null,
            'lida_em' => null,
        ];
    }

    public function lida(): static
    {
        return $this->state(fn (): array => ['lida_em' => now()]);
    }
}
