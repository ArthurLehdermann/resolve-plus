<?php

namespace Database\Seeders;

use App\Categories\Models\Categoria;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seed das 5 categorias do MVP (INV-080).
 *
 * Definições canônicas em `database/fixtures/categorias_mvp.json` (D3).
 */
class CategoriaSeeder extends Seeder
{
    public const FIXTURE = 'database/fixtures/categorias_mvp.json';

    /**
     * @return list<array{
     *     codigo: string,
     *     nome: string,
     *     descricao: string,
     *     ativo: bool,
     *     template_escopo: array<string, mixed>
     * }>
     */
    public static function definitions(): array
    {
        $path = database_path('fixtures/categorias_mvp.json');
        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException('Fixture ausente: '.$path);
        }

        /** @var list<array{codigo: string, nome: string, descricao: string, ativo: bool, template_escopo: array<string, mixed>}> $categorias */
        $categorias = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $categorias;
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            Categoria::query()->updateOrCreate(
                ['codigo' => $definition['codigo']],
                [
                    'nome' => $definition['nome'],
                    'descricao' => $definition['descricao'],
                    'ativo' => $definition['ativo'],
                    'template_escopo' => $definition['template_escopo'],
                ],
            );
        }
    }
}
