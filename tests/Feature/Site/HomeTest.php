<?php

namespace Tests\Feature\Site;

use App\Categories\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_html_with_the_product_promise(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Resolve+')
            ->assertSee('preço, profissional e garantia', false);
    }

    /**
     * A lista de serviços da página sai do banco, não de texto escrito na
     * view: categoria desativada no admin some do site sozinha.
     */
    public function test_home_lists_active_categories_only(): void
    {
        Categoria::factory()->mvp('eletrica')->create();
        Categoria::factory()->inativa()->create(['nome' => 'Categoria Desativada']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Elétrica', false)
            ->assertDontSee('Categoria Desativada', false);
    }

    /**
     * O site é o oficial em qualquer ambiente: nada de bloquear buscador nem
     * de carimbar aviso de homologação na página. Decisão do PO em 23/08/2026,
     * antes de apontar o domínio definitivo.
     */
    public function test_home_is_indexable_and_has_no_environment_banner(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('noindex', false)
            ->assertDontSee('homologação', false);
    }

    /**
     * O tratador de exceções devolve JSON para a API mesmo sem Accept — o que
     * antes valia para o site inteiro e transformava erro de página em objeto
     * JSON no navegador.
     */
    public function test_api_still_answers_json_without_accept_header(): void
    {
        $this->get('/api/v1/rota-que-nao-existe')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
