<?php

namespace App\Requests;

/**
 * Valida Solicitacao.escopo contra Categoria.template_escopo (INV-080).
 */
final class EscopoTemplateValidator
{
    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $escopo
     * @return array<string, list<string>>
     */
    public function validate(array $template, array $escopo): array
    {
        $errors = [];

        foreach ($template as $campo => $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $obrigatorio = (bool) ($spec['obrigatorio'] ?? false);
            $presente = array_key_exists($campo, $escopo) && ! $this->isBlank($escopo[$campo]);

            if ($obrigatorio && ! $presente) {
                $errors[$campo][] = 'Campo obrigatório do template de escopo ausente.';

                continue;
            }

            if (! $presente) {
                continue;
            }

            $tipoErrors = $this->validateTipo($campo, $spec, $escopo[$campo]);

            if ($tipoErrors !== []) {
                $errors[$campo] = [
                    ...($errors[$campo] ?? []),
                    ...$tipoErrors,
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function validateTipo(string $campo, array $spec, mixed $valor): array
    {
        $tipo = (string) ($spec['tipo'] ?? 'string');

        return match ($tipo) {
            'int' => $this->validateInt($valor, $spec),
            'number' => $this->validateNumber($valor, $spec),
            'bool' => is_bool($valor) ? [] : ['O campo '.$campo.' deve ser booleano.'],
            'enum' => $this->validateEnum($campo, $valor, $spec),
            'string' => is_string($valor) && $valor !== '' ? [] : ['O campo '.$campo.' deve ser texto.'],
            default => ['Tipo de campo de escopo não suportado: '.$tipo],
        };
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function validateInt(mixed $valor, array $spec): array
    {
        if (! is_int($valor)) {
            return ['Deve ser um inteiro.'];
        }

        if (isset($spec['min']) && is_numeric($spec['min']) && $valor < (int) $spec['min']) {
            return ['Deve ser no mínimo '.(int) $spec['min'].'.'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function validateNumber(mixed $valor, array $spec): array
    {
        if (! is_int($valor) && ! is_float($valor)) {
            return ['Deve ser um número.'];
        }

        if (isset($spec['min']) && is_numeric($spec['min']) && $valor < (float) $spec['min']) {
            return ['Deve ser no mínimo '.(float) $spec['min'].'.'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function validateEnum(string $campo, mixed $valor, array $spec): array
    {
        if (! is_string($valor) || $valor === '') {
            return ['O campo '.$campo.' deve ser um dos valores permitidos.'];
        }

        $valores = $spec['valores'] ?? [];

        if (! is_array($valores) || ! in_array($valor, $valores, true)) {
            return ['Valor não permitido para o campo '.$campo.'.'];
        }

        return [];
    }

    private function isBlank(mixed $valor): bool
    {
        return $valor === null || $valor === '';
    }
}
