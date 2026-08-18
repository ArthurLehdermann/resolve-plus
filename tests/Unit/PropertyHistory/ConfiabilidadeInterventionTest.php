<?php

namespace Tests\Unit\PropertyHistory;

use App\PropertyHistory\ConfiabilidadeIntervention;
use App\PropertyHistory\OrigemIntervention;
use PHPUnit\Framework\TestCase;

class ConfiabilidadeInterventionTest extends TestCase
{
    public function test_confiabilidade_e_derivada_da_origem(): void
    {
        $this->assertSame(
            ConfiabilidadeIntervention::Alta,
            ConfiabilidadeIntervention::fromOrigem(OrigemIntervention::Plataforma),
        );
        $this->assertSame(
            ConfiabilidadeIntervention::Media,
            ConfiabilidadeIntervention::fromOrigem(OrigemIntervention::Importado),
        );
        $this->assertSame(
            ConfiabilidadeIntervention::Baixa,
            ConfiabilidadeIntervention::fromOrigem(OrigemIntervention::Manual),
        );
    }
}
