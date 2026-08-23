<?php

namespace App\Site\Http\Controllers;

use App\Categories\Models\Categoria;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SiteController extends Controller
{
    /**
     * Página pública do produto.
     *
     * As categorias vêm do banco, não de uma lista escrita na página: é a
     * mesma fonte que o app usa em GET /categories, então site e produto não
     * divergem quando alguém ativa ou desativa uma categoria no admin.
     */
    public function home(): View
    {
        $categorias = Categoria::query()
            ->ativas()
            ->orderBy('nome')
            ->get(['codigo', 'nome', 'descricao']);

        return view('site.home', [
            'categorias' => $categorias,
            ...$this->dadosDoLayout(),
        ]);
    }

    public function privacidade(): View
    {
        return view('site.privacidade', $this->dadosDoLayout());
    }

    public function termos(): View
    {
        return view('site.termos', $this->dadosDoLayout());
    }

    /**
     * O layout do site (cabeçalho e rodapé) precisa destes três em qualquer
     * página. Passar aqui, e não por composer global, mantém explícito de onde
     * a view tira cada endereço.
     *
     * @return array{appUrl: string, emailContato: string, emailEncarregado: string, controlador: string}
     */
    private function dadosDoLayout(): array
    {
        return [
            'appUrl' => config('site.app_url'),
            'emailContato' => config('site.email_contato'),
            'emailEncarregado' => config('site.email_encarregado'),
            'controlador' => config('site.controlador'),
        ];
    }
}
