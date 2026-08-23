{{--
    Termos de Uso.

    Cada prazo e percentual aqui sai de uma decisão registrada e do valor que
    está mesmo na tabela `configuracoes`: aceite automático em 72h (ADR-004),
    multa de cancelamento 10/25/50% e mediação em 7 dias
    (foundation/03-cancellation-rules.md), comissão de 10% (COMISSAO_PERCENT),
    garantia no modelo B — profissional responde primeiro e a plataforma media
    (ADR-003) — e o mecanismo antidesintermediação (specifications/09), com a
    suspensão automática em 5 tentativas por 90 dias (ContactLeakEnforcer) e o
    desfecho padrão da disputa que estoura o prazo (ResolveExpiredDisputes).

    Mudou a configuração, muda este texto. Número aqui é promessa ao usuário,
    não ilustração.
--}}
@extends('site.layout')

@section('titulo', 'Termos de Uso — Resolve+')
@section('descricao', 'As regras de uso do Resolve+ para clientes e profissionais: contratação, pagamento, cancelamento, mediação, garantia e responsabilidades.')

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
                <strong>Quem executa o serviço é o profissional</strong>, de forma autônoma:
                não há vínculo empregatício entre ele e a plataforma, e a plataforma não
                presta o serviço de reparo.
            </p>

            <h2>2. Conta</h2>
            <ul>
                <li>É preciso ter 18 anos ou mais e informar dados verdadeiros.</li>
                <li>A entrada é sem senha, por código enviado ao seu e-mail ou por conta Google. O código é pessoal e intransferível.</li>
                <li>O profissional escolhe as categorias que atende e já consegue ver as solicitações abertas nelas. <strong>Enviar proposta e receber o aviso de solicitação nova, só depois que os documentos forem verificados</strong> pela nossa equipe.</li>
            </ul>

            <h2>3. Solicitação, propostas e contratação</h2>
            <ul>
                <li>O cliente descreve o problema e a plataforma mostra uma faixa de preço estimada — referência para orientar a conversa, não proposta nem compromisso de valor.</li>
                <li>Os profissionais verificados que atendem aquela categoria respondem sobre o mesmo escopo. A escolha é do cliente.</li>
                <li>O aceite da proposta cria o serviço, abre o chat entre as duas partes e inicia o pagamento. Endereço e telefone não são repassados ao profissional: o combinado — inclusive como chegar ao imóvel — passa pelo chat do serviço.</li>
            </ul>

            <h2>4. Pagamento, comissão e repasse</h2>
            <ul>
                <li>O pagamento é feito pela plataforma, por meio do nosso provedor de pagamento. No cartão, o valor é <strong>autorizado</strong> no aceite; no Pix, a cobrança é gerada no aceite e confirmada pelo provedor.</li>
                <li>O dinheiro <strong>não vai para o profissional na hora</strong>: o repasse só acontece depois que o cliente aprova a conclusão.</li>
                <li>Concluído o serviço, o cliente tem <strong>72 horas</strong> para aprovar ou contestar. Sem manifestação nesse prazo, o serviço é aprovado automaticamente e o valor é repassado.</li>
                <li>A plataforma retém uma <strong>comissão de 10%</strong> do valor do serviço; o profissional recebe o restante. A comissão pode mudar, e a alíquota vigente no momento fica registrada em cada pagamento.</li>
            </ul>

            <h2>5. Cancelamento</h2>
            <ul>
                <li><strong>Antes de aceitar uma proposta:</strong> o cliente cancela a solicitação livremente, sem custo — nenhum pagamento foi iniciado.</li>
                <li><strong>Depois do aceite e antes de o serviço começar:</strong> o cancelamento é do cliente, com multa proporcional à antecedência em relação à data agendada, calculada sobre o valor da proposta: <strong>10%</strong> com 48 horas ou mais de antecedência, <strong>25%</strong> entre 24 e 48 horas e <strong>50%</strong> com menos de 24 horas. Só a multa é cobrada; o restante é liberado. <strong>Enquanto não houver data marcada não há multa</strong> — sem calendário não há antecedência a medir.</li>
                <li><strong>Com o serviço em andamento:</strong> não há cancelamento direto, para nenhuma das partes. Quem quiser encerrar abre uma contestação e o caso vai para mediação.</li>
                <li><strong>Depois da conclusão aprovada:</strong> não existe cancelamento; o que existe é contestação e garantia.</li>
            </ul>

            <h2>6. Contestação e mediação</h2>
            <p>
                Aberta a contestação, a plataforma analisa o caso e decide em até
                <strong>7 dias</strong>, ouvindo as duas partes e considerando o que está
                registrado no serviço (escopo, mensagens, fotos e agenda). A decisão pode
                liberar o valor ao profissional, devolvê-lo ao cliente ou dividi-lo. Nenhum
                repasse acontece enquanto a contestação estiver aberta.
            </p>
            <p>
                <strong>Se esse prazo passar sem decisão nossa, o caso se encerra sozinho</strong>,
                pelo desfecho que menos surpreende quem estava esperando: contestação de um
                serviço concluído é <em>aprovada</em> e o valor vai para o profissional;
                pedido de cancelamento durante a execução é <em>aceito</em> e a cobrança é
                liberada sem custo para o cliente. Foi a plataforma que perdeu o prazo, e
                nenhuma das partes fica com o dinheiro travado por causa disso. Você pode
                pedir revisão pelo suporte.
            </p>

            <h2>7. Garantia</h2>
            <p>
                Todo serviço concluído registra prazo de garantia, com fotos e descrição do
                que foi feito. <strong>O profissional responde primeiro</strong> pelo reparo
                dentro do prazo; a plataforma media quando o problema não se resolve entre as
                partes. Os direitos do Código de Defesa do Consumidor continuam valendo em
                qualquer hipótese.
            </p>

            <h2>8. Negociar por fora não é permitido</h2>
            <p>
                Combinar o serviço fora da plataforma tira do cliente o pagamento protegido, a
                garantia registrada e o histórico do imóvel — e é motivo de suspensão. Por
                isso o texto de propostas e mensagens passa por um filtro automático que
                oculta telefones, e-mails e perfis de redes sociais.
            </p>
            <p>
                Cada tentativa fica registrada, e a régua é esta:
                <strong>cinco tentativas em 90 dias suspendem a conta automaticamente</strong>,
                sem análise prévia. A suspensão pode ser revista — escreva para o suporte e
                uma pessoa reexamina o caso.
            </p>

            <h2>9. Conduta e avaliações</h2>
            <ul>
                <li>Não publicar conteúdo ilegal, ofensivo ou de terceiro sem autorização.</li>
                <li>Não usar a plataforma para fraude, cobrança indevida ou avaliação falsa.</li>
                <li>Avaliações devem refletir a experiência real com o serviço. O histórico de serviços aprovados, avaliações, cancelamentos e reclamações alimenta o nível de confiança do profissional, que aparece para o cliente junto de cada proposta.</li>
            </ul>

            <h2>10. Suspensão e encerramento</h2>
            <p>
                Podemos suspender ou encerrar contas que descumpram estes termos, com aviso
                sempre que possível. Você pode encerrar a sua conta quando quiser, em
                <em>Perfil</em> no aplicativo — respeitados os serviços em
                andamento e os pagamentos em aberto.
            </p>

            <h2>11. Responsabilidade</h2>
            <p>
                A plataforma responde pelo funcionamento do que ela oferece: publicação da
                solicitação, intermediação do pagamento, registro da garantia e mediação. A
                execução técnica, a qualidade e a segurança do reparo são de responsabilidade
                do profissional contratado, sem prejuízo da mediação descrita nos itens 6 e 7
                e das garantias legais do consumidor.
            </p>

            <h2>12. Dados pessoais</h2>
            <p>
                O tratamento de dados está descrito na
                <a href="{{ route('site.privacidade') }}">Política de Privacidade</a>, que faz
                parte destes termos.
            </p>

            <h2>13. Mudanças e foro</h2>
            <p>
                Estes termos podem mudar; a data no topo indica a versão vigente, e mudança
                relevante é avisada no aplicativo. Aplica-se a lei brasileira, e fica eleito o
                foro do domicílio do consumidor para as questões de consumo.
            </p>

            <p>Dúvidas: <a href="mailto:{{ $emailContato }}">{{ $emailContato }}</a>.</p>
        </div>
    </section>

@endsection
