{{--
    Política de Privacidade.

    O texto descreve o que o sistema faz de verdade — cada dado listado existe
    numa tabela do banco ou numa integração ativa (pagamento, login Google,
    armazenamento de arquivos, e-mail). Antes de acrescentar promessa aqui,
    confira se o código já cumpre; e quando o tratamento mudar, esta página
    muda junto.
--}}
@extends('site.layout')

@section('titulo', 'Política de Privacidade — Resolve+')
@section('descricao', 'Como o Resolve+ trata os dados de clientes e profissionais: o que coletamos, por quê, com quem compartilhamos, por quanto tempo guardamos e como exercer seus direitos de titular.')

@section('conteudo')

    <section class="secao documento">
        <div class="container documento-conteudo">
            <span class="secao-etiqueta">Privacidade</span>
            <h1>Política de Privacidade</h1>
            <p class="documento-data">Vigente desde 23/08/2026</p>

            <p>
                Esta política explica como o Resolve+ trata dados pessoais de quem contrata
                serviços (cliente) e de quem os executa (profissional). Ela vale para o site,
                o aplicativo e o painel administrativo.
            </p>

            <h2>1. Quem é o controlador</h2>
            <p>
                O Resolve+ é operado pela {{ $controlador }}. Para assuntos de proteção de
                dados, fale com o nosso encarregado:
                <a href="mailto:{{ $emailEncarregado }}">{{ $emailEncarregado }}</a>.
            </p>

            <h2>2. Dados que tratamos</h2>
            <ul>
                <li><strong>Cadastro:</strong> nome, e-mail, telefone, foto de perfil e o tipo de conta (cliente ou profissional).</li>
                <li><strong>Entrada na conta:</strong> a entrada é sem senha. Enviamos um código/link de uso único para o seu e-mail ou, se você escolher o Google, recebemos daquele login apenas nome, e-mail e foto.</li>
                <li><strong>Imóvel:</strong> CEP, logradouro, número, complemento, bairro, cidade, estado, o apelido que você dá ao lugar e, se você informar, as coordenadas do endereço.</li>
                <li><strong>Solicitação de serviço:</strong> a descrição do problema, as fotos que você anexa, a categoria e o imóvel a que se refere.</li>
                <li><strong>Profissional:</strong> documentos enviados para verificação (identificação, comprovantes e, quando houver, número e vigência de apólice) e as categorias de serviço que ele atende.</li>
                <li><strong>Execução:</strong> propostas, mensagens trocadas dentro da plataforma, agendamentos, registro de conclusão, fotos de antes e depois e os dados da garantia.</li>
                <li><strong>Pagamento:</strong> valores, situação da cobrança e os identificadores gerados pelo nosso provedor de pagamento. <strong>Não guardamos número de cartão</strong> — esses dados são tratados diretamente pelo provedor.</li>
                <li><strong>Reputação:</strong> avaliações, notas e o nível de confiança calculado a partir do histórico.</li>
                <li><strong>Registros técnicos:</strong> data, hora, endereço IP e ação em eventos sensíveis (auditoria), além de tokens de sessão e do histórico de notificações enviadas.</li>
            </ul>

            <h2>3. Mensagens e propostas passam por filtro automático</h2>
            <p>
                Para que a negociação e a garantia fiquem dentro da plataforma, o texto das
                propostas e das mensagens passa por um filtro que oculta telefones, e-mails e
                perfis de redes sociais antes de entregar ao destinatário. O texto original
                fica registrado apenas para auditoria interna e apuração de abuso, com acesso
                restrito à nossa equipe, e é apagado quando você exclui a sua conta. Não
                usamos esse conteúdo para publicidade nem o entregamos a terceiros para
                análise comercial.
            </p>

            <h2>4. Para que usamos</h2>
            <ul>
                <li>Criar e manter a sua conta e permitir a entrada sem senha.</li>
                <li>Publicar a solicitação para os profissionais que atendem aquela categoria — o aviso de solicitação nova vai só para os já verificados — e permitir propostas comparáveis.</li>
                <li>Executar a contratação: agenda, comunicação, conclusão, pagamento e garantia.</li>
                <li>Verificar documentos de profissionais antes de liberar o acesso às solicitações.</li>
                <li>Calcular reputação e nível de confiança.</li>
                <li>Prevenir fraude, abuso e tentativa de levar o combinado para fora da plataforma.</li>
                <li>Cumprir obrigações legais e fiscais e responder a autoridades quando exigido.</li>
            </ul>

            <h2>5. Bases legais</h2>
            <ul>
                <li><strong>Execução de contrato</strong> (art. 7º, V da LGPD): cadastro, contratação, pagamento, garantia e suporte.</li>
                <li><strong>Cumprimento de obrigação legal</strong> (art. 7º, II): guarda de registros fiscais e de acesso.</li>
                <li><strong>Legítimo interesse</strong> (art. 7º, IX): segurança, prevenção a fraude e integridade da plataforma, com minimização dos dados usados.</li>
                <li><strong>Consentimento</strong> (art. 7º, I): quando pedirmos algo além disso, sempre de forma destacada e revogável.</li>
            </ul>

            <h2>6. Com quem compartilhamos</h2>
            <p>Não vendemos dados pessoais. Compartilhamos o necessário para o serviço acontecer:</p>
            <ul>
                <li><strong>Entre cliente e profissional:</strong> o profissional vê a descrição, o escopo e as fotos da solicitação. <strong>Endereço e telefone do cliente não são entregues a ele</strong> — nem antes, nem depois do aceite: a combinação acontece pelo chat do serviço, dentro da plataforma. O cliente, por sua vez, vê o nome, a nota média e o nível de confiança de quem envia proposta.</li>
                <li><strong>Provedor de pagamento:</strong> processa a cobrança e o repasse.</li>
                <li><strong>Provedor de e-mail:</strong> envia códigos de entrada e avisos do serviço.</li>
                <li><strong>Hospedagem e armazenamento de arquivos:</strong> mantêm o banco de dados e as fotos e documentos enviados.</li>
                <li><strong>Google:</strong> apenas se você escolher entrar com a conta Google.</li>
                <li><strong>Autoridades:</strong> mediante requisição legal.</li>
            </ul>

            <h2>7. Onde os dados ficam</h2>
            <p>
                Os arquivos enviados (fotos e documentos) ficam em armazenamento em nuvem na
                região do Brasil. Parte da infraestrutura de servidores e dos provedores de
                e-mail e pagamento pode operar fora do país; nesses casos a transferência
                internacional segue o art. 33 da LGPD, com cláusulas contratuais que garantem
                nível de proteção equivalente.
            </p>

            <h2>8. Decisões automatizadas</h2>
            <p>Três coisas acontecem sem ninguém da nossa equipe olhar caso a caso:</p>
            <ul>
                <li>
                    <strong>Nível de confiança do profissional</strong>, calculado a partir de
                    serviços aprovados, avaliações, cancelamentos e reclamações. Ele é exibido
                    ao cliente junto da proposta; não filtra quais solicitações o profissional
                    enxerga.
                </li>
                <li>
                    <strong>Filtro de contatos e suspensão por reincidência.</strong> Além de
                    ocultar o contato (item 3), cada tentativa fica registrada. Cinco
                    tentativas em 90 dias <strong>suspendem a conta automaticamente</strong>.
                </li>
                <li>
                    <strong>Prazos que se cumprem sozinhos.</strong> Serviço concluído e não
                    contestado em 72 horas é aprovado e o valor repassado; contestação sem
                    decisão nossa em 7 dias se encerra pelo desfecho padrão descrito nos
                    Termos de Uso. Nesses casos o dinheiro se move sem análise humana, porque
                    o prazo venceu.
                </li>
            </ul>
            <p>
                Exclusão de conta por nossa iniciativa e decisão de mediação dentro do prazo
                são sempre humanas. Em qualquer das situações acima você pode pedir revisão
                pelo e-mail do encarregado (art. 20 da LGPD), e um analista reexamina o caso.
            </p>

            <h2>9. Por quanto tempo guardamos</h2>
            <p>
                Enquanto a sua conta existir. Ao excluir a conta, apagamos ou anonimizamos os
                seus dados de identificação — nome, e-mail, telefone, foto, documentos
                enviados e sessões abertas — e mantemos apenas o que a lei exige ou o que
                sustenta direito de terceiro: registros de serviço, pagamento e garantia
                seguem guardados pelos prazos legais aplicáveis (em regra, cinco anos para
                registros fiscais e financeiros), já sem identificar você. O histórico
                permanece ligado ao imóvel, não à pessoa.
            </p>
            <p>
                Dado transitório sai antes disso, por rotina diária: códigos de entrada
                vencidos, tokens expirados, chaves de idempotência e os payloads brutos
                recebidos do provedor de pagamento.
            </p>

            <h2>10. Seus direitos e como exercer</h2>
            <p>Como titular, você pode pedir confirmação e acesso, correção, anonimização, portabilidade, eliminação e informação sobre compartilhamentos, além de revogar consentimento.</p>
            <ul>
                <li><strong>No aplicativo:</strong> no fim da tela de <em>Perfil</em> você baixa uma cópia dos seus dados e pode excluir a sua conta, sem precisar falar com ninguém.</li>
                <li><strong>Por e-mail:</strong> <a href="mailto:{{ $emailEncarregado }}">{{ $emailEncarregado }}</a> — atendemos o pedido com o mesmo alcance da opção do app.</li>
            </ul>
            <p>
                Respondemos em até 15 dias. Se houver serviço em andamento ou pagamento em
                aberto, é preciso concluir ou cancelar essa etapa antes de excluir a conta — o
                app avisa quando for o caso. Você também pode reclamar à ANPD (Autoridade
                Nacional de Proteção de Dados).
            </p>

            <h2>11. Segurança</h2>
            <p>
                Tráfego criptografado (HTTPS), senha substituída por código de uso único,
                acesso por token com expiração, permissões por perfil e registro de auditoria
                das ações sensíveis.
            </p>
            <p>
                Sobre os arquivos, vale a distinção: <strong>documento de profissional só sai
                pelo backend autenticado</strong>, nunca por link direto. Já as fotos de
                solicitação e de garantia e a foto de perfil ficam em endereços de leitura
                pública, com nome sorteado e não divulgado — quem tiver o link abre o arquivo.
                Não publique nas fotos documento, tela de aplicativo bancário ou qualquer
                coisa que você não mostraria a um desconhecido.
            </p>

            <h2>12. Cookies e armazenamento local</h2>
            <p>
                O site não usa cookie de publicidade nem rastreador de terceiros. Em qualquer
                visita ele grava dois cookies técnicos, necessários para o funcionamento da
                página: o identificador de sessão e o token que protege formulários contra
                falsificação de requisição. Guarda também, no armazenamento local do
                navegador, a sua preferência de tema (claro ou escuro). O aplicativo não usa
                cookie: o token de acesso fica guardado no próprio aparelho e some quando você
                sai da conta.
            </p>

            <h2>13. Menores de idade</h2>
            <p>O Resolve+ é para maiores de 18 anos. Não coletamos dados de crianças e adolescentes de forma intencional.</p>

            <h2>14. Mudanças nesta política</h2>
            <p>Se o tratamento mudar, atualizamos esta página e a data no topo. Mudança relevante é avisada no aplicativo.</p>
        </div>
    </section>

@endsection
