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
            'appUrl' => config('site.app_url'),
            'emailContato' => config('site.email_contato'),
        ]);
    }
}
