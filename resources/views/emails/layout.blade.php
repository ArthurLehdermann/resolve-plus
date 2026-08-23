{{--
    Moldura de todo e-mail do Resolve+: fundo, marca, cartão e rodapé.

    E-mail não é página: cada cliente (Gmail, Outlook, Apple Mail) roda um
    motor diferente e nenhum garante CSS externo. Por isso três escolhas que
    parecem retrógradas e são deliberadas:

    - Layout em <table>, não em <div> com flex/grid: o Outlook para Windows
      renderiza com o motor do Word, que ignora quase todo posicionamento
      moderno e só é confiável em tabela.
    - Estilo inline nos elementos que importam: parte dos clientes descarta o
      bloco <style>, então a versão inline é o piso — o <style> abaixo só
      acrescenta o que é opcional (tela estreita e modo escuro).
    - Paleta espelhando `AuthColors` do app (lib/widgets/auth_shell.dart). Quem
      recebe o código acabou de ver a tela de entrada; o e-mail é a mesma cena.
--}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    {{-- Impede o iOS Mail de reescalar a fonte por conta própria. --}}
    <meta name="x-apple-disable-message-reformatting" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title>@yield('titulo', 'Resolve+')</title>
    <style>
        /*
         * Modo escuro vale onde o cliente respeita a preferência do sistema
         * (Apple Mail, Outlook para Mac). O Gmail ignora esta consulta e
         * inverte as cores sozinho — daí o cuidado de não usar branco puro
         * nem preto puro, que são o que ele mais estraga ao inverter.
         */
        @media (prefers-color-scheme: dark) {
            .rp-bg { background-color: #080C0D !important; }
            .rp-card { background-color: #11181A !important; border-color: #1E2729 !important; }
            .rp-titulo, .rp-marca { color: #F1F5F4 !important; }
            .rp-texto { color: #8C9B99 !important; }
            .rp-faint { color: #5F6E6C !important; }
            .rp-caixa { background-color: #0C1213 !important; border-color: #242E30 !important; }
            .rp-codigo { color: #2DD4BF !important; }
            .rp-divisor { border-color: #1E2729 !important; }
            a { color: #2DD4BF !important; }
        }

        @media only screen and (max-width: 600px) {
            .rp-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .rp-codigo { font-size: 30px !important; letter-spacing: 8px !important; text-indent: 8px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#F3F7F6; -webkit-font-smoothing:antialiased;" class="rp-bg">

{{--
    Prévia que o cliente mostra na lista, ao lado do assunto. Sem isto ele
    inventa a prévia com o primeiro texto do corpo — no nosso caso, a saudação.
    Os caracteres invisíveis no fim empurram o resto do HTML para fora da
    prévia, senão o cliente completa a linha com o que vier depois.
--}}
<div style="display:none; font-size:1px; color:#F3F7F6; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
    @yield('preheader')
    &#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3F7F6;" class="rp-bg">
    <tr>
        <td align="center" style="padding:32px 12px 44px;">

            <table role="presentation" width="520" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:520px;">

                {{-- Marca: mesmo selo da tela de entrada, montado em HTML para
                     não depender de imagem — cliente que bloqueia imagem por
                     padrão abriria o e-mail sem cabeçalho nenhum. --}}
                <tr>
                    <td style="padding:0 4px 20px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td width="44" style="width:44px;">
                                    {{-- O degradê é enfeite: quem não o
                                         suporta (Outlook) fica com o teal
                                         sólido, que já contrasta com a tinta
                                         escura do "R+". --}}
                                    <table role="presentation" width="44" cellpadding="0" cellspacing="0" border="0" style="width:44px;">
                                        <tr>
                                            <td align="center" height="44" style="height:44px; border-radius:13px; background-color:#14B8A6; background-image:linear-gradient(135deg, #2DD4BF 0%, #0F766E 100%); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:18px; font-weight:800; letter-spacing:-0.5px; color:#04231F;">R+</td>
                                        </tr>
                                    </table>
                                </td>
                                <td style="padding-left:12px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:19px; font-weight:700; letter-spacing:-0.3px; color:#0C1615;" class="rp-marca">Resolve+</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="background-color:#FFFFFF; border:1px solid #E3EAE9; border-radius:16px;" class="rp-card">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:36px 40px;" class="rp-pad">
                                    @yield('conteudo')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 8px 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; font-size:12px; line-height:19px; color:#8B9A98;" class="rp-faint">
                        @yield('rodape')
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
