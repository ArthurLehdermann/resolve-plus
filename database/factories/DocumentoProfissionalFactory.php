<?php

namespace Database\Factories;

use App\Auth\Models\Usuario;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Enums\TipoDocumentoProfissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoProfissional>
 */
class DocumentoProfissionalFactory extends Factory
{
    protected $model = DocumentoProfissional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profissional_id' => Usuario::factory()->profissional(),
            'tipo' => TipoDocumentoProfissional::IdentidadeFiscal,
            'arquivo' => 'documents/'.fake()->uuid().'.jpg',
            'status' => StatusDocumentoProfissional::Pendente,
            'motivo_rejeicao' => null,
            'revisado_por_id' => null,
            'revisado_em' => null,
            'apolice_numero' => null,
            'vigencia_inicio' => null,
            'vigencia_fim' => null,
        ];
    }

    public function aprovado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusDocumentoProfissional::Aprovado,
        ]);
    }
}
