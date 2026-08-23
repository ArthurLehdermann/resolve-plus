{{--
    Página pública do Resolve+.

    Texto colado na promessa que os documentos de produto fazem
    (docs/specifications/01-visao-geral.md): orçamento, propostas comparáveis,
    pagamento protegido, garantia registrada e prontuário do imóvel. Nada aqui
    promete recurso que o app ainda não faz.
--}}
@php
    $ehProducao = app()->environment('production');
    $versaoCss = @filemtime(public_path('site/site.css')) ?: 1;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resolve+ — serviços para a sua casa, com preço, profissional e garantia no mesmo lugar</title>
    <meta name="description" content="Descreva o problema, receba propostas comparáveis de profissionais verificados, pague com o valor protegido até a conclusão e guarde a garantia do serviço no histórico do seu imóvel.">
    <meta name="theme-color" content="#0F766E">

    {{-- Homologação não entra em buscador: o mesmo conteúdo em dois domínios
         concorreria com o site de produção. --}}
    @unless ($ehProducao)
        <meta name="robots" content="noindex, nofollow">
    @endunless

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Resolve+">
    <meta property="og:title" content="Resolve+ — do problema à solução, no mesmo lugar">
    <meta property="og:description" content="Propostas comparáveis de profissionais verificados, pagamento protegido e garantia registrada.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">

    <link rel="icon" href="{{ asset('site/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('site/site.css') }}?v={{ $versaoCss }}">
</head>
<body>

<a class="pular-para-conteudo" href="#conteudo">Pular para o conteúdo</a>

@unless ($ehProducao)
    <div class="faixa-ambiente">Ambiente de homologação — dados e serviços aqui são de teste.</div>
@endunless

<header class="cabecalho">
    <div class="container cabecalho-conteudo">
        <a class="marca" href="{{ route('site.home') }}">
            <span class="marca-selo" aria-hidden="true">R+</span>
            Resolve+
        </a>

        <nav class="menu" aria-label="Seções da página">
            <a href="#como-funciona">Como funciona</a>
            <a href="#servicos">Serviços</a>
            <a href="#confianca">Confiança</a>
            <a href="#profissionais">Para profissionais</a>
        </nav>

        <a class="btn btn-primario" href="{{ $appUrl }}">Abrir o app</a>
    </div>
</header>

<main id="conteudo">

    <section class="hero">
        <div class="container hero-grade">
            <div>
                <span class="selo-topo">
                    <span class="ponto" aria-hidden="true"></span>
                    Elétrica, hidráulica, pintura, montagem e pequenos reparos
                </span>

                <h1>Do problema à solução, com <span class="destaque">preço, profissional e garantia</span> no mesmo lugar.</h1>

                <p class="hero-texto">
                    Descreva o que precisa, receba propostas sobre o mesmo escopo e contrate
                    sem depender de indicação de grupo de WhatsApp. O valor fica protegido até
                    o serviço ser concluído, e a garantia fica registrada.
                </p>

                <div class="hero-acoes">
                    <a class="btn btn-primario" href="{{ $appUrl }}">Pedir um orçamento</a>
                    <a class="btn btn-secundario" href="#profissionais">Sou profissional</a>
                </div>

                <p class="hero-nota">Entrada por código de e-mail ou Google — sem senha para criar nem lembrar.</p>
            </div>

            <div class="cartao-demo" aria-hidden="true">
                <div class="cartao-demo-topo">
                    <div>
                        <div class="cartao-demo-titulo">Tomada da cozinha esquentando</div>
                        <div class="cartao-demo-sub">Elétrica &middot; 3 propostas recebidas</div>
                    </div>
                    <span class="etiqueta">Escopo padronizado</span>
                </div>

                <div class="proposta destacada">
                    <div>
                        <div class="proposta-quem">Marcos A.</div>
                        <div class="proposta-detalhe">Verificado &middot; nota 9,4 &middot; 38 serviços</div>
                    </div>
                    <div class="proposta-valor">R$ 280</div>
                </div>

                <div class="proposta">
                    <div>
                        <div class="proposta-quem">Juliana R.</div>
                        <div class="proposta-detalhe">Verificado &middot; nota 9,1 &middot; 22 serviços</div>
                    </div>
                    <div class="proposta-valor">R$ 320</div>
                </div>

                <div class="proposta">
                    <div>
                        <div class="proposta-quem">Eletro Sul</div>
                        <div class="proposta-detalhe">Verificado &middot; nota 8,8 &middot; 61 serviços</div>
                    </div>
                    <div class="proposta-valor">R$ 410</div>
                </div>

                <div class="cartao-demo-rodape">
                    Pagamento retido pela plataforma e liberado ao profissional depois que
                    você aceita a conclusão.
                </div>
            </div>
        </div>
    </section>

    <section class="secao secao-alt" id="como-funciona">
        <div class="container">
            <div class="secao-cabeca">
                <span class="secao-etiqueta">Como funciona</span>
                <h2>Quatro passos, do problema à garantia</h2>
                <p>
                    O mesmo caminho para um reparo urgente ou para a manutenção que você
                    vinha adiando.
                </p>
            </div>

            <div class="grade grade-4">
                <article class="cartao">
                    <div class="passo-numero">1</div>
                    <h3>Descreva o problema</h3>
                    <p>Fotos e uma descrição do que está acontecendo. Antes de falar com alguém, você já vê uma faixa de preço estimada.</p>
                </article>

                <article class="cartao">
                    <div class="passo-numero">2</div>
                    <h3>Receba propostas comparáveis</h3>
                    <p>Profissionais da categoria certa respondem sobre o mesmo escopo — dá para comparar valor com valor, não orçamento com orçamento.</p>
                </article>

                <article class="cartao">
                    <div class="passo-numero">3</div>
                    <h3>Contrate com o valor protegido</h3>
                    <p>O pagamento fica retido na plataforma e só é liberado depois que você aceita a conclusão do serviço.</p>
                </article>

                <article class="cartao">
                    <div class="passo-numero">4</div>
                    <h3>Guarde a garantia</h3>
                    <p>Fotos de antes e depois, o que foi feito e o prazo de cobertura ficam registrados no histórico do imóvel.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="secao" id="servicos">
        <div class="container">
            <div class="secao-cabeca">
                <span class="secao-etiqueta">Serviços</span>
                <h2>O que dá para resolver hoje</h2>
                <p>
                    Estamos começando por uma cidade e por estas categorias, para atender bem
                    antes de atender muito.
                </p>
            </div>

            <div class="chips">
                @foreach ($categorias as $categoria)
                    <span class="chip">{{ $categoria->nome }}</span>
                @endforeach
            </div>
        </div>
    </section>

    <section class="secao secao-alt" id="confianca">
        <div class="container">
            <div class="secao-cabeca">
                <span class="secao-etiqueta">Confiança</span>
                <h2>A plataforma fica na transação inteira</h2>
                <p>
                    Marketplace de indicação some depois que passa o contato. Aqui o
                    compromisso continua até a garantia.
                </p>
            </div>

            <div class="grade grade-3">
                <article class="cartao">
                    <h3>Profissional verificado</h3>
                    <p>Documentos conferidos pela nossa equipe antes de receber qualquer solicitação, e nível de confiança que sobe com serviço aprovado e avaliação.</p>
                </article>

                <article class="cartao">
                    <h3>Pagamento protegido</h3>
                    <p>O valor é autorizado no início e retido: se o serviço não for concluído como combinado, existe mediação antes de qualquer repasse.</p>
                </article>

                <article class="cartao">
                    <h3>Prontuário do imóvel</h3>
                    <p>Todo serviço feito vira histórico: o que foi trocado, por quem, quando e com qual garantia. Serve para a próxima manutenção e na hora de vender.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="secao" id="profissionais">
        <div class="container">
            <div class="faixa-pro">
                <div>
                    <h2>Para quem executa o serviço</h2>
                    <p>
                        Demanda com escopo definido, cliente que já sabe a faixa de preço e
                        pagamento que não depende de cobrança no fim do mês.
                    </p>
                </div>

                <ul class="lista-pro">
                    <li><span class="marcador" aria-hidden="true">&check;</span> Solicitações da sua categoria e da sua região</li>
                    <li><span class="marcador" aria-hidden="true">&check;</span> Valor autorizado antes de você começar</li>
                    <li><span class="marcador" aria-hidden="true">&check;</span> Selo de verificado e histórico que vale reputação</li>
                    <li><span class="marcador" aria-hidden="true">&check;</span> Registro do que você entregou, com foto e garantia</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <h2>Tem algo para resolver na sua casa?</h2>
            <p>
                Leva um minuto para descrever o problema. Você recebe as propostas e decide
                sem compromisso.
            </p>

            <div class="cta-acoes">
                <a class="btn btn-primario" href="{{ $appUrl }}">Abrir o app</a>
                <a class="btn btn-secundario" href="mailto:{{ $emailContato }}">Falar com a gente</a>
            </div>
        </div>
    </section>

</main>

<footer class="rodape">
    <div class="container rodape-conteudo">
        <div class="rodape-texto">
            &copy; {{ date('Y') }} Resolve+ &middot; desenvolvido por
            <a href="https://bigworks.com.br">BigWorks</a>
        </div>

        <nav class="rodape-links" aria-label="Links do rodapé">
            <a href="#como-funciona">Como funciona</a>
            <a href="#profissionais">Para profissionais</a>
            <a href="{{ $appUrl }}">Entrar</a>
            <a href="mailto:{{ $emailContato }}">{{ $emailContato }}</a>
        </nav>
    </div>
</footer>

</body>
</html>
