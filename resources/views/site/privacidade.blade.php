{{--
    Política de Privacidade.

    O texto descreve o que o sistema faz de verdade — cada dado listado existe
    numa tabela do banco ou numa integração ativa (pagamento, login Google,
    armazenamento de arquivos). Antes de acrescentar promessa aqui, confira se
    o código já cumpre; e quando o tratamento mudar, esta página muda junto.
--}}
@extends('site.layout')

@section('titulo', 'Política de Privacidade — Resolve+')
@section('descricao', 'Como o Resolve+ trata os dados de clientes e profissionais: o que coletamos, por quê, com quem compartilhamos e como exercer seus direitos de titular.')

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
                <li><strong>Imóvel:</strong> CEP, logradouro, número, complemento, bairro, cidade, estado e o apelido que você dá ao lugar.</li>
                <li><strong>Solicitação de serviço:</strong> a descrição do problema, as fotos que você anexa, a categoria e o imóvel a que se refere.</li>
                <li><strong>Profissional:</strong> documentos enviados para verificação (identificação, comprovantes e, quando houver, apólice e vigência), categorias atendidas e região de atuação.</li>
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
                restrito à nossa equipe. Não usamos esse conteúdo para publicidade nem o
                entregamos a terceiros para análise comercial.
            </p>

            <h2>4. Para que usamos</h2>
            <ul>
                <li>Criar e manter a sua conta e permitir a entrada sem senha.</li>
                <li>Publicar a solicitação para profissionais da categoria e região certas e permitir propostas comparáveis.</li>
                <li>Executar a contratação: agenda, comunicação, conclusão, pagamento e garantia.</li>
                <li>Verificar documentos de profissionais antes de liberar o acesso às solicitações.</li>
                <li>Calcular reputação e nível de confiança.</li>
                <li>Prevenir fraude, abuso e tentativa de levar o combinado para fora da plataforma.</li>
                <li>Cumprir obrigações legais, fiscais e responder a autoridades quando exigido.</li>
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
                <li><strong>Entre cliente e profissional:</strong> o profissional vê a descrição, as fotos e a região da solicitação; o endereço completo e o contato só são liberados depois do aceite da proposta.</li>
                <li><strong>Provedor de pagamento:</strong> processa a cobrança e o repasse.</li>
                <li><strong>Provedor de e-mail:</strong> envia códigos de entrada e avisos do serviço.</li>
                <li><strong>Hospedagem e armazenamento de arquivos:</strong> mantêm o banco de dados e as fotos e documentos enviados.</li>
                <li><strong>Google:</strong> apenas se você escolher entrar com a conta Google.</li>
                <li><strong>Autoridades:</strong> mediante requisição legal.</li>
            </ul>

            <h2>7. Por quanto tempo guardamos</h2>
            <p>
                Enquanto a sua conta existir. Ao excluir a conta, apagamos ou anonimizamos os
                seus dados de identificação e mantemos apenas o que a lei exige ou o que
                sustenta direito de terceiro: registros de serviço, pagamento e garantia
                seguem guardados pelos prazos legais, sem identificar você além do necessário.
                O histórico do imóvel permanece de forma despersonalizada, ligado ao imóvel e
                não à pessoa. Cópias de segurança são rotacionadas periodicamente.
            </p>

            <h2>8. Seus direitos e como exercer</h2>
            <p>Como titular, você pode pedir confirmação e acesso, correção, anonimização, portabilidade, eliminação e informação sobre compartilhamentos, além de revogar consentimento.</p>
            <ul>
                <li><strong>No aplicativo:</strong> em <em>Perfil &rsaquo; Privacidade</em> você baixa uma cópia dos seus dados e pode excluir a sua conta.</li>
                <li><strong>Por e-mail:</strong> <a href="mailto:{{ $emailEncarregado }}">{{ $emailEncarregado }}</a>.</li>
            </ul>
            <p>
                Respondemos em até 15 dias. Se houver serviço em andamento ou pagamento em
                aberto, concluímos ou cancelamos essa etapa antes de excluir a conta — e
                avisamos você quando for o caso.
            </p>

            <h2>9. Segurança</h2>
            <p>
                Tráfego criptografado (HTTPS), senha substituída por código de uso único,
                acesso por token com expiração, permissões por perfil, arquivos em
                armazenamento com acesso restrito e registro de auditoria das ações
                sensíveis.
            </p>

            <h2>10. Cookies e armazenamento local</h2>
            <p>
                O site não usa cookie de publicidade nem rastreador de terceiros. Guardamos no
                seu navegador apenas a preferência de tema (claro/escuro) e, quando você entra
                na conta, os cookies e o token necessários para manter a sessão.
            </p>

            <h2>11. Menores de idade</h2>
            <p>O Resolve+ é para maiores de 18 anos. Não coletamos dados de crianças e adolescentes de forma intencional.</p>

            <h2>12. Mudanças nesta política</h2>
            <p>Se o tratamento mudar, atualizamos esta página e a data no topo. Mudança relevante é avisada no aplicativo.</p>
        </div>
    </section>

@endsection
