<?php

namespace App\Requests;

use App\Admin\Configuracao;
use App\Categories\Models\Categoria;
use App\Requests\Exceptions\RequestException;

/**
 * Heurística de faixa de preço do MVP (10-motor-precificacao.md). Cold start
 * sem histórico: tabela de referência por categoria+cidade, ajustada por
 * fatores em basis points declarados no template_escopo da categoria.
 */
final class PricingEngine
{
    /**
     * @param  array<string, mixed>  $escopo
     */
    public function estimate(Categoria $categoria, string $cidade, array $escopo): PricingResult
    {
        $tabela = TabelaPreco::query()
            ->where('categoria_id', $categoria->id)
            ->where('cidade', $cidade)
            ->where('ativo', true)
            ->first();

        if ($tabela === null) {
            throw RequestException::precoTabelaAusente();
        }

        $fatorBp = $this->fatorTotalBp($categoria->template_escopo ?? [], $escopo);
        $arredondamento = Configuracao::inteiro('PRECO_ARREDONDAMENTO_CENTAVOS');

        $min = $this->arredondar(intdiv($tabela->valor_min * $fatorBp, 10000), $arredondamento);
        $max = $this->arredondar(intdiv($tabela->valor_max * $fatorBp, 10000), $arredondamento);

        if ($min < 1) {
            $min = $arredondamento;
        }

        if ($max < $min) {
            $max = $min;
        }

        if ($max === $min) {
            $max = $min + $arredondamento;
        }

        return new PricingResult($min, $max, $fatorBp, $tabela->id);
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $escopo
     */
    private function fatorTotalBp(array $template, array $escopo): int
    {
        $fatorBp = 10000;

        foreach ($template as $campo => $spec) {
            if (! is_array($spec) || ! isset($spec['ajuste_preco']) || ! is_array($spec['ajuste_preco'])) {
                continue;
            }

            if (! array_key_exists($campo, $escopo)) {
                continue;
            }

            $fatorCampoBp = $this->fatorCampoBp($spec['ajuste_preco'], $escopo[$campo]);
            $fatorBp = intdiv($fatorBp * $fatorCampoBp, 10000);
        }

        return $fatorBp;
    }

    /**
     * @param  array<string, mixed>  $ajuste
     */
    private function fatorCampoBp(array $ajuste, mixed $valor): int
    {
        $tipo = (string) ($ajuste['tipo'] ?? '');

        return match ($tipo) {
            'enum' => $this->fatorEnum($ajuste, $valor),
            'linear' => $this->fatorLinear($ajuste, $valor),
            'bool' => $this->fatorBool($ajuste, $valor),
            default => 10000,
        };
    }

    /**
     * @param  array<string, mixed>  $ajuste
     */
    private function fatorEnum(array $ajuste, mixed $valor): int
    {
        if (! is_string($valor)) {
            return 10000;
        }

        $mapa = $ajuste['mapa'] ?? [];

        if (! is_array($mapa) || ! isset($mapa[$valor]) || ! is_numeric($mapa[$valor])) {
            return 10000;
        }

        return (int) $mapa[$valor];
    }

    /**
     * @param  array<string, mixed>  $ajuste
     */
    private function fatorLinear(array $ajuste, mixed $valor): int
    {
        if (! is_int($valor) && ! is_float($valor)) {
            return 10000;
        }

        $base = is_numeric($ajuste['base'] ?? null) ? (float) $ajuste['base'] : 0.0;
        $porUnidadeBp = is_numeric($ajuste['por_unidade_bp'] ?? null) ? (int) $ajuste['por_unidade_bp'] : 0;
        $fatorMinBp = is_numeric($ajuste['fator_min_bp'] ?? null) ? (int) $ajuste['fator_min_bp'] : 0;
        $fatorMaxBp = is_numeric($ajuste['fator_max_bp'] ?? null) ? (int) $ajuste['fator_max_bp'] : PHP_INT_MAX;

        $fatorBp = 10000 + (int) round($porUnidadeBp * max(0.0, $valor - $base));

        return max($fatorMinBp, min($fatorMaxBp, $fatorBp));
    }

    /**
     * @param  array<string, mixed>  $ajuste
     */
    private function fatorBool(array $ajuste, mixed $valor): int
    {
        if ($valor !== true) {
            return 10000;
        }

        $seTrueBp = $ajuste['se_true_bp'] ?? null;

        return is_numeric($seTrueBp) ? (int) $seTrueBp : 10000;
    }

    private function arredondar(int $valor, int $arredondamento): int
    {
        if ($arredondamento <= 0) {
            return $valor;
        }

        return (int) round($valor / $arredondamento) * $arredondamento;
    }
}
