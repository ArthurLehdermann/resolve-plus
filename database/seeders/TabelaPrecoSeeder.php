<?php

namespace Database\Seeders;

use App\Categories\Models\Categoria;
use App\Requests\TabelaPreco;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seed da tabela de preço bootstrap (10-motor-precificacao.md §2.1) das 5
 * categorias do MVP na cidade piloto. Valores são chutes operacionais do
 * Admin, não verdade de mercado. Depende de CategoriaSeeder já ter rodado.
 */
class TabelaPrecoSeeder extends Seeder
{
    public const FIXTURE = 'database/fixtures/tabelas_preco_mvp.json';

    /**
     * @return list<array{categoria_codigo: string, cidade: string, valor_min: int, valor_max: int}>
     */
    public static function definitions(): array
    {
        $path = database_path('fixtures/tabelas_preco_mvp.json');
        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException('Fixture ausente: '.$path);
        }

        /** @var list<array{categoria_codigo: string, cidade: string, valor_min: int, valor_max: int}> $tabelas */
        $tabelas = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $tabelas;
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            $categoria = Categoria::query()->where('codigo', $definition['categoria_codigo'])->first();

            if ($categoria === null) {
                throw new RuntimeException('Categoria MVP desconhecida para tabela de preço: '.$definition['categoria_codigo']);
            }

            TabelaPreco::query()->updateOrCreate(
                ['categoria_id' => $categoria->id, 'cidade' => $definition['cidade']],
                [
                    'valor_min' => $definition['valor_min'],
                    'valor_max' => $definition['valor_max'],
                    'ativo' => true,
                ],
            );
        }
    }
}
