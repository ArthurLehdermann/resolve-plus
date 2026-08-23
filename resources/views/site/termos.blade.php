{{--
    Termos de Uso.

    Os prazos e regras aqui saem das decisões registradas: aceite automático em
    72h (ADR-004), garantia no modelo B — profissional responde primeiro e a
    plataforma media (ADR-003) — e o mecanismo antidesintermediação
    (specifications/09). Mudou a decisão, muda este texto.
--}}
@extends('site.layout')

@section('titulo', 'Termos de Uso — Resolve+')
@section('descricao', 'As regras de uso do Resolve+ para clientes e profissionais: contratação, pagamento, prazos, garantia e responsabilidades.')

@section('conteudo')

    <section class="secao documento">
        <div class="container documento-conteudo">
            <span class="secao-etiqueta">Termos</span>
            <h1>Termos de Uso</h1>
            <p class="documento-data">Vigentes desde 23/08/2026</p>

            <p>
                Ao usar o Resolve+ você concorda com estas regras. Elas valem para o site, o
                aplicativo e o painel, tanto para quem contrata (cliente) quanto para quem
                executa o serviço (profissional).
            </p>

            <h2>1. O que a plataforma faz</h2>
            <p>
                O Resolve+ aproxima clientes e profissionais, padroniza o escopo do serviço,
                intermedia o pagamento e registra a garantia e o histórico do imóvel.
                <strong>Quem executa o serviço é o profissional</strong>, de forma autônoma;
                não há vínculo empregatício entre ele e a plataforma.
            </p>

            <h2>2. Conta</h2>
            <ul>
                <li>É preciso ter 18 anos ou mais e informar dados verdadeiros.</li>
                <li>A entrada é sem senha, por código enviado ao seu e-mail ou por conta Google. O código é pessoal e intransferível.</li>
                <li>Profissional só recebe solicitações depois que os documentos enviados forem verificados pela nossa equipe.</li>
            </ul>

            <h2>3. Solicitação, propostas e contratação</h2>
            <ul>
                <li>O cliente descreve o problema; a plataforma mostra uma faixa de preço estimada, que é referência, não proposta.</li>
                <li>Profissionais da categoria e região respondem sobre o mesmo escopo. A escolha é do cliente.</li>
                <li>O aceite da proposta cria o serviço, libera o contato entre as partes e inicia o pagamento.</li>
            </ul>

            <h2>4. Pagamento e liberação</h2>
            <ul>
                <li>O pagamento é feito pela plataforma, por meio do nosso provedor de pagamento, e fica bloqueado até a conclusão.</li>
                <li>Concluído o serviço, o cliente tem <strong>72 horas</strong> para aprovar ou contestar. Sem manifestação nesse prazo, o serviço é aprovado automaticamente e o valor é repassado ao profissional.</li>
                <li>Contestação dentro do prazo abre mediação antes de qualquer repasse.</li>
                <li>A plataforma cobra comissão sobre o serviço, informada antes do aceite.</li>
            </ul>

            <h2>5. Cancelamento</h2>
            <p>
                Cancelamento antes do início do serviço não gera cobrança. Depois de iniciado,
                o que já foi executado pode ser cobrado proporcionalmente, com mediação da
                plataforma em caso de divergência.
            </p>

            <h2>6. Garantia</h2>
            <p>
                Todo serviço concluído registra prazo de garantia, com fotos e descrição do
                que foi feito. <strong>O profissional responde primeiro</strong> pelo reparo
                dentro do prazo; a plataforma media o conflito quando ele não é resolvido
                entre as partes. Os direitos do Código de Defesa do Consumidor continuam
                valendo em qualquer hipótese.
            </p>

            <h2>7. Negociar por fora não é permitido</h2>
            <p>
                Combinar o serviço fora da plataforma tira do cliente o pagamento protegido, a
                garantia registrada e o histórico — e é motivo de suspensão. Por isso o texto
                de propostas e mensagens passa por filtro automático que oculta telefones,
                e-mails e perfis de redes sociais antes do aceite.
            </p>

            <h2>8. Conduta</h2>
            <ul>
                <li>Não publicar conteúdo ilegal, ofensivo ou de terceiro sem autorização.</li>
                <li>Não usar a plataforma para fraude, cobrança indevida ou avaliação falsa.</li>
                <li>Avaliações devem refletir a experiência real com o serviço.</li>
            </ul>

            <h2>9. Suspensão e encerramento</h2>
            <p>
                Podemos suspender ou encerrar contas que descumpram estes termos, com aviso
                sempre que possível. Você pode encerrar a sua conta quando quiser, em
                <em>Perfil &rsaquo; Privacidade</em> no aplicativo — respeitados os serviços em
                andamento e os pagamentos em aberto.
            </p>

            <h2>10. Responsabilidade</h2>
            <p>
                A plataforma responde pelo funcionamento do serviço que oferece — publicação
                da solicitação, intermediação do pagamento, registro da garantia e mediação.
                A execução técnica e a qualidade do reparo são de responsabilidade do
                profissional contratado, sem prejuízo da mediação descrita no item 6.
            </p>

            <h2>11. Dados pessoais</h2>
            <p>
                O tratamento de dados está descrito na
                <a href="{{ route('site.privacidade') }}">Política de Privacidade</a>, que faz
                parte destes termos.
            </p>

            <h2>12. Mudanças e foro</h2>
            <p>
                Estes termos podem mudar; a data no topo indica a versão vigente e mudança
                relevante é avisada no aplicativo. Aplica-se a lei brasileira, e fica eleito o
                foro do domicílio do consumidor para as questões de consumo.
            </p>

            <p>Dúvidas: <a href="mailto:{{ $emailContato }}">{{ $emailContato }}</a>.</p>
        </div>
    </section>

@endsection
