<?php

namespace Tests\Unit\PropertyHistory;

use App\PropertyHistory\ChaveEndereco;
use PHPUnit\Framework\TestCase;

class ChaveEnderecoTest extends TestCase
{
    public function test_normalizes_cep_numero_and_complemento(): void
    {
        $this->assertSame(
            '01310200|100|APTO101',
            ChaveEndereco::from('01310-200', '100', 'Apto 101'),
        );
    }

    public function test_strips_accents_punctuation_and_is_case_insensitive(): void
    {
        $this->assertSame(
            ChaveEndereco::from('01310-200', '100', 'Apto 101'),
            ChaveEndereco::from('01310.200', '100', 'apto-101'),
        );
        $this->assertSame(
            '90035191|50|BLOCOA',
            ChaveEndereco::from('90.035-191', '50', 'Bloco Á'),
        );
    }

    public function test_empty_complemento_keeps_trailing_separator(): void
    {
        $this->assertSame('01310200|100|', ChaveEndereco::from('01310-200', '100'));
        $this->assertSame('01310200|100|', ChaveEndereco::from('01310-200', '100', '  '));
    }
}
