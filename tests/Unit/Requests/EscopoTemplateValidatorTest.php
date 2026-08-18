<?php

namespace Tests\Unit\Requests;

use App\Requests\EscopoTemplateValidator;
use PHPUnit\Framework\TestCase;

class EscopoTemplateValidatorTest extends TestCase
{
    private EscopoTemplateValidator $validator;

    /** @var array<string, mixed> */
    private array $pintura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new EscopoTemplateValidator;
        $this->pintura = [
            'comodos' => [
                'tipo' => 'int',
                'obrigatorio' => true,
                'rotulo' => 'Cômodos',
                'min' => 1,
            ],
            'area_m2' => [
                'tipo' => 'number',
                'obrigatorio' => true,
                'rotulo' => 'Área',
                'min' => 1,
            ],
            'tipo_tinta' => [
                'tipo' => 'enum',
                'obrigatorio' => true,
                'rotulo' => 'Tinta',
                'valores' => ['LATEX_PVA', 'ACRILICA'],
            ],
            'paredes_ou_teto' => [
                'tipo' => 'enum',
                'obrigatorio' => true,
                'rotulo' => 'Superfície',
                'valores' => ['PAREDES', 'TETO', 'PAREDES_E_TETO'],
            ],
        ];
    }

    public function test_missing_required_field_fails_inv_080(): void
    {
        $errors = $this->validator->validate($this->pintura, [
            'comodos' => 2,
            'area_m2' => 35.5,
            'tipo_tinta' => 'LATEX_PVA',
        ]);

        $this->assertArrayHasKey('paredes_ou_teto', $errors);
    }

    public function test_valid_pintura_scope_passes(): void
    {
        $errors = $this->validator->validate($this->pintura, [
            'comodos' => 2,
            'area_m2' => 35.5,
            'tipo_tinta' => 'LATEX_PVA',
            'paredes_ou_teto' => 'PAREDES_E_TETO',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_invalid_enum_and_min_fail(): void
    {
        $errors = $this->validator->validate($this->pintura, [
            'comodos' => 0,
            'area_m2' => 35.5,
            'tipo_tinta' => 'OLEO',
            'paredes_ou_teto' => 'PAREDES',
        ]);

        $this->assertArrayHasKey('comodos', $errors);
        $this->assertArrayHasKey('tipo_tinta', $errors);
    }

    public function test_optional_field_may_be_omitted(): void
    {
        $template = [
            'tipo_reparo' => [
                'tipo' => 'enum',
                'obrigatorio' => true,
                'rotulo' => 'Tipo',
                'valores' => ['OUTRO'],
            ],
            'area_m2' => [
                'tipo' => 'number',
                'obrigatorio' => false,
                'rotulo' => 'Área',
                'min' => 0,
            ],
        ];

        $errors = $this->validator->validate($template, [
            'tipo_reparo' => 'OUTRO',
        ]);

        $this->assertSame([], $errors);
    }
}
