<?php

namespace Tests\Feature\Requests;

use App\Categories\Models\Categoria;
use App\Requests\Exceptions\RequestException;
use App\Requests\PricingEngine;
use App\Requests\TabelaPreco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_without_ajuste_preco_returns_tabela_range_unchanged(): void
    {
        $categoria = Categoria::factory()->mvp('pintura')->create();
        TabelaPreco::factory()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
            'valor_min' => 30000,
            'valor_max' => 150000,
        ]);

        $resultado = app(PricingEngine::class)->estimate($categoria, 'São Paulo', [
            'comodos' => 2,
            'area_m2' => 35.5,
            'tipo_tinta' => 'LATEX_PVA',
            'paredes_ou_teto' => 'PAREDES_E_TETO',
        ]);

        $this->assertSame(30000, $resultado->min);
        $this->assertSame(150000, $resultado->max);
        $this->assertSame(10000, $resultado->fatorBp);
    }

    public function test_estimate_applies_linear_enum_and_bool_ajuste_preco(): void
    {
        $categoria = Categoria::factory()->create([
            'template_escopo' => [
                'comodos' => [
                    'tipo' => 'int',
                    'obrigatorio' => true,
                    'ajuste_preco' => [
                        'tipo' => 'linear',
                        'base' => 1,
                        'por_unidade_bp' => 1500,
                        'fator_min_bp' => 10000,
                        'fator_max_bp' => 25000,
                    ],
                ],
                'tipo_tinta' => [
                    'tipo' => 'enum',
                    'obrigatorio' => true,
                    'valores' => ['latex', 'epoxi'],
                    'ajuste_preco' => [
                        'tipo' => 'enum',
                        'mapa' => ['latex' => 10000, 'epoxi' => 13000],
                    ],
                ],
                'precisa_andaime' => [
                    'tipo' => 'bool',
                    'obrigatorio' => true,
                    'ajuste_preco' => [
                        'tipo' => 'bool',
                        'se_true_bp' => 12000,
                    ],
                ],
            ],
        ]);
        TabelaPreco::factory()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
            'valor_min' => 100000,
            'valor_max' => 200000,
        ]);

        // comodos=3 -> 10000 + 1500*(3-1) = 13000; tinta epoxi -> 13000; andaime -> 12000.
        // fatorBp = 13000 * 13000 / 10000 = 16900; * 12000 / 10000 = 20280.
        $resultado = app(PricingEngine::class)->estimate($categoria, 'São Paulo', [
            'comodos' => 3,
            'tipo_tinta' => 'epoxi',
            'precisa_andaime' => true,
        ]);

        $this->assertSame(20280, $resultado->fatorBp);
        // min = round(100000*20280/10000 / 1000) * 1000 = 203000
        $this->assertSame(203000, $resultado->min);
        // max = round(200000*20280/10000 / 1000) * 1000 = 406000
        $this->assertSame(406000, $resultado->max);
    }

    public function test_estimate_throws_preco_tabela_ausente_when_no_active_row(): void
    {
        $categoria = Categoria::factory()->mvp('eletrica')->create();

        $this->expectException(RequestException::class);

        app(PricingEngine::class)->estimate($categoria, 'São Paulo', [
            'tipo_servico' => 'DIAGNOSTICO',
            'quantidade_pontos' => 1,
        ]);
    }

    public function test_estimate_ignores_inactive_price_table_row(): void
    {
        $categoria = Categoria::factory()->mvp('eletrica')->create();
        TabelaPreco::factory()->inativa()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
        ]);

        $this->expectException(RequestException::class);

        app(PricingEngine::class)->estimate($categoria, 'São Paulo', [
            'tipo_servico' => 'DIAGNOSTICO',
            'quantidade_pontos' => 1,
        ]);
    }

    public function test_estimate_never_returns_a_single_point_range(): void
    {
        $categoria = Categoria::factory()->mvp('eletrica')->create();
        TabelaPreco::factory()->create([
            'categoria_id' => $categoria->id,
            'cidade' => 'São Paulo',
            'valor_min' => 1000,
            'valor_max' => 1000,
        ]);

        $resultado = app(PricingEngine::class)->estimate($categoria, 'São Paulo', [
            'tipo_servico' => 'DIAGNOSTICO',
            'quantidade_pontos' => 1,
        ]);

        $this->assertGreaterThan($resultado->min, $resultado->max);
    }
}
