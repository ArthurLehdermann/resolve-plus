<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CategoriaMvpFixtureTest extends TestCase
{
    private const CODIGOS_MVP = [
        'eletrica',
        'hidraulica',
        'pintura',
        'pequenos_reparos',
        'montagem',
    ];

    private const TIPOS = ['int', 'number', 'enum', 'bool', 'string'];

    public function test_fixture_defines_five_mvp_category_templates(): void
    {
        $path = dirname(__DIR__, 2).'/database/fixtures/categorias_mvp.json';
        $this->assertFileExists($path);

        $json = file_get_contents($path);
        $this->assertNotFalse($json);

        $categorias = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($categorias);
        $this->assertCount(5, $categorias);

        $codigos = array_column($categorias, 'codigo');
        $this->assertEqualsCanonicalizing(self::CODIGOS_MVP, $codigos);

        foreach ($categorias as $categoria) {
            $this->assertIsString($categoria['nome']);
            $this->assertNotSame('', $categoria['nome']);
            $this->assertTrue($categoria['ativo']);
            $this->assertIsArray($categoria['template_escopo']);
            $this->assertNotEmpty($categoria['template_escopo']);

            $temObrigatorio = false;

            foreach ($categoria['template_escopo'] as $campo => $spec) {
                $this->assertIsString($campo);
                $this->assertArrayHasKey('tipo', $spec);
                $this->assertContains($spec['tipo'], self::TIPOS);
                $this->assertArrayHasKey('obrigatorio', $spec);
                $this->assertIsBool($spec['obrigatorio']);

                if ($spec['obrigatorio'] === true) {
                    $temObrigatorio = true;
                }

                if ($spec['tipo'] === 'enum') {
                    $this->assertArrayHasKey('valores', $spec);
                    $this->assertIsArray($spec['valores']);
                    $this->assertNotEmpty($spec['valores']);
                }
            }

            $this->assertTrue($temObrigatorio, $categoria['codigo'].' precisa de ao menos um campo obrigatório');
        }
    }
}
