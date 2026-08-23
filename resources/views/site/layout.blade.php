{{--
    Layout do site público do Resolve+.

    A home e os documentos legais dividem cabeçalho, rodapé e o botão de tema:
    quem abre a política de privacidade continua no mesmo site, com a mesma
    barra e a mesma preferência de claro/escuro.
--}}
@php
    $versaoCss = @filemtime(public_path('site/site.css')) ?: 1;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Resolve+ — serviços para a sua casa, com preço, profissional e garantia no mesmo lugar')</title>
    <meta name="description" content="@yield('descricao', 'Descreva o problema, receba propostas comparáveis de profissionais verificados, pague com o valor protegido até a conclusão e guarde a garantia do serviço no histórico do seu imóvel.')">
    <meta name="theme-color" content="#0F766E">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Resolve+">
    <meta property="og:title" content="@yield('titulo', 'Resolve+ — do problema à solução, no mesmo lugar')">
    <meta property="og:description" content="@yield('descricao', 'Propostas comparáveis de profissionais verificados, pagamento protegido e garantia registrada.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">

    <link rel="icon" href="{{ asset('site/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('site/site.css') }}?v={{ $versaoCss }}">

    {{--
        Roda antes da primeira pintura para a página não piscar branco em quem
        já escolheu o escuro. Sem escolha salva o site é claro: o padrão é o
        do produto, não o do sistema operacional.
    --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('tema') === 'escuro') {
                    document.documentElement.setAttribute('data-tema', 'escuro');
                }
            } catch (e) {
                /* navegador com storage bloqueado fica no claro */
            }
        })();
    </script>
</head>
<body>

<a class="pular-para-conteudo" href="#conteudo">Pular para o conteúdo</a>

<header class="cabecalho">
    <div class="container cabecalho-conteudo">
        <a class="marca" href="{{ route('site.home') }}">
            <span class="marca-selo" aria-hidden="true">R+</span>
            Resolve+
        </a>

        <nav class="menu" aria-label="Seções do site">
            <a href="{{ route('site.home') }}#como-funciona">Como funciona</a>
            <a href="{{ route('site.home') }}#servicos">Serviços</a>
            <a href="{{ route('site.home') }}#confianca">Confiança</a>
            <a href="{{ route('site.home') }}#profissionais">Para profissionais</a>
        </nav>

        <div class="cabecalho-acoes">
            <button type="button" class="botao-tema" data-alternar-tema
                    aria-label="Usar tema escuro" title="Usar tema escuro">
                <svg class="icone-sol" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4.2"/>
                    <path d="M12 2.6v2.2M12 19.2v2.2M4.2 12H2M22 12h-2.2M6.2 6.2 4.7 4.7M19.3 19.3l-1.5-1.5M17.8 6.2l1.5-1.5M4.7 19.3l1.5-1.5"/>
                </svg>
                <svg class="icone-lua" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.5 14.3A8.5 8.5 0 1 1 9.7 3.5a6.8 6.8 0 0 0 10.8 10.8Z"/>
                </svg>
            </button>

            <a class="btn btn-primario" href="{{ $appUrl }}">Abrir o app</a>
        </div>
    </div>
</header>

<main id="conteudo">
    @yield('conteudo')
</main>

<footer class="rodape">
    <div class="container rodape-conteudo">
        <div class="rodape-texto">
            &copy; {{ date('Y') }} Resolve+ &middot; desenvolvido por
            <a href="https://bigworks.com.br">BigWorks</a>
        </div>

        <nav class="rodape-links" aria-label="Links do rodapé">
            <a href="{{ route('site.privacidade') }}">Privacidade</a>
            <a href="{{ route('site.termos') }}">Termos de uso</a>
            <a href="{{ $appUrl }}">Entrar</a>
            <a href="mailto:{{ $emailContato }}">{{ $emailContato }}</a>
        </nav>
    </div>
</footer>

<script>
    // Alterna o tema e guarda a escolha. A chave é a mesma palavra que o app
    // grava ('claro'/'escuro'), mas o storage é por domínio: site e app não
    // compartilham a preferência.
    (function () {
        var botao = document.querySelector('[data-alternar-tema]');
        if (!botao) return;

        var raiz = document.documentElement;
        var metaCor = document.querySelector('meta[name="theme-color"]');

        function rotular() {
            var escuro = raiz.getAttribute('data-tema') === 'escuro';
            var texto = escuro ? 'Usar tema claro' : 'Usar tema escuro';

            botao.setAttribute('aria-label', texto);
            botao.setAttribute('title', texto);

            if (metaCor) {
                metaCor.setAttribute('content', escuro ? '#080c0d' : '#0F766E');
            }
        }

        botao.addEventListener('click', function () {
            var escuro = raiz.getAttribute('data-tema') !== 'escuro';

            if (escuro) {
                raiz.setAttribute('data-tema', 'escuro');
            } else {
                raiz.removeAttribute('data-tema');
            }

            try {
                localStorage.setItem('tema', escuro ? 'escuro' : 'claro');
            } catch (e) {
                /* sem storage a escolha vale só nesta visita */
            }

            rotular();
        });

        rotular();
    })();
</script>

</body>
</html>
