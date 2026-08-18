<?php

namespace Tests\Unit\Payments;

use App\Payments\SplitCalculator;
use PHPUnit\Framework\TestCase;

class SplitCalculatorTest extends TestCase
{
    public function test_calculates_platform_and_professional_shares(): void
    {
        $split = (new SplitCalculator)->calculate(10_000, 10);

        $this->assertSame(1_000, $split['valor_plataforma']);
        $this->assertSame(9_000, $split['valor_profissional']);
        $this->assertSame(10.0, $split['aliquota_vigente']);
    }
}
