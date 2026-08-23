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
     * Fora de produção o site não pode ser indexado: homologação e produção
     * servem o mesmo conteúdo e disputariam a mesma busca.
     */
    public function test_home_is_noindex_outside_production(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('noindex', false);
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
