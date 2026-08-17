<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fixture das 5 categorias do MVP (INV-080).
 *
 * Persistência no banco fica para a issue de Categorias (model/tabela ainda não existem).
 * Até lá, `definitions()` é o contrato carregável a partir de `database/fixtures/categorias_mvp.json`.
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
        $categorias = self::definitions();

        $this->command?->info(sprintf(
            '%d categorias MVP lidas de %s (persistência pendente da issue de Categorias).',
            count($categorias),
            self::FIXTURE,
        ));
    }
}
