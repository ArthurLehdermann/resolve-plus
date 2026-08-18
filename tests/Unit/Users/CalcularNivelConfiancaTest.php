<?php

namespace Tests\Unit\Users;

use App\Users\CalcularNivelConfianca;
use App\Users\NivelConfianca;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalcularNivelConfiancaTest extends TestCase
{
    #[DataProvider('casos')]
    public function test_escolhe_maior_nivel_cujos_limiares_sao_atendidos(
        int $servicos,
        ?int $notaDez,
        int $taxaCancel,
        int $dias,
        int $reclamacoes,
        NivelConfianca $esperado,
    ): void {
        $nivel = (new CalcularNivelConfianca)(
            $servicos,
            $notaDez,
            $taxaCancel,
            $dias,
            $reclamacoes,
        );

        $this->assertSame($esperado, $nivel);
    }

    /**
     * @return array<string, array{0: int, 1: ?int, 2: int, 3: int, 4: int, 5: NivelConfianca}>
     */
    public static function casos(): array
    {
        return [
            'sem avaliacao permanece verificado' => [50, null, 0, 400, 0, NivelConfianca::Verificado],
            'abaixo de bronze' => [2, 50, 0, 40, 0, NivelConfianca::Verificado],
            'bronze' => [3, 40, 20, 30, 1, NivelConfianca::Bronze],
            'prata' => [10, 43, 15, 90, 0, NivelConfianca::Prata],
            'ouro' => [25, 45, 10, 180, 0, NivelConfianca::Ouro],
            'elite' => [50, 47, 5, 365, 0, NivelConfianca::Elite],
            'elite falha por reclamacao' => [50, 50, 0, 400, 1, NivelConfianca::Bronze],
            'metade do caminho para prata vira bronze' => [10, 42, 10, 100, 0, NivelConfianca::Bronze],
        ];
    }
}
