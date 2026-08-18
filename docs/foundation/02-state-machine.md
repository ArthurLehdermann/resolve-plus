# 02: State Machine

> v0.1, rascunho formalizando os estados já listados em `specifications/02-funcionalidades.md` (seção 10) com regras explícitas de transição: quem pode disparar, sob qual condição, o que a transição gera. Fonte de verdade para transição é este documento; `00-domain-invariants.md` continua sendo fonte de verdade para as regras que **nunca** podem ser violadas.

## Convenção

`Estado A --(ator: condição)--> Estado B [efeito]`

## 1. Solicitação

```
Criada --(sistema: automático)--> Aberta [notifica profissionais elegíveis, RN010/RF011]
Aberta --(profissional: envia proposta)--> Recebendo Propostas
Aberta --(sistema: nenhuma proposta em prazo configurável)--> Expirada [FA001]
Recebendo Propostas --(cliente: aceita proposta)--> Contratada [INV-010/011/012, dispara P1+P2 do Event Storm]
Recebendo Propostas --(cliente: cancela)--> Cancelada [FA002, só antes de proposta aceita]
Aberta|Recebendo Propostas --(cliente: cancela)--> Cancelada [FA002]
```

- Não existe transição de `Cancelada` ou `Expirada` para qualquer outro estado (terminal).
- `Contratada` é o gatilho para a criação do `Serviço`, a Solicitação em si não tem mais transições após isso (INV-021).

## 2. Proposta

```
Enviada --(cliente: aceita, e solicitação em Aberta/Recebendo Propostas)--> Aceita [INV-012]
Enviada --(sistema: outra proposta da mesma solicitação é aceita)--> Recusada [INV-011, automático]
Enviada --(profissional: retira antes do aceite)--> Retirada
```

- `Aceita` é terminal e única por solicitação (INV-010).
- Não existe transição para `Aceita` se a Solicitação já estiver `Cancelada`/`Expirada` (INV-012).

## 3. Serviço

```
(criado a partir de PropostaAceita) --> Agendado [INV-021]
Agendado --(profissional: comparece e inicia)--> Em Andamento
Em Andamento --(profissional: registra conclusão)--> Aguardando Aprovação [FP003]
Aguardando Aprovação --(cliente: confirma)--> Aprovado [P4/P5 do Event Storm: captura pagamento + emite garantia]
Aguardando Aprovação --(sistema: janela de aceite automático expira sem contestação)--> Aprovado [AUTO_APPROVAL_HOURS = 72h, adr/ADR-004-prazo-aceite-automatico.md]
Aguardando Aprovação --(cliente: contesta)--> Em Contestação [FA004, PaymentDispute CONTESTACAO_CONCLUSAO]
Em Contestação --(admin: resolve CONTESTACAO_CONCLUSAO, resultado=APROVADO)--> Aprovado [captura integral]
Em Contestação --(admin: resolve CONTESTACAO_CONCLUSAO, resultado=CANCELADO)--> Cancelado [libera autorização]
Em Contestação --(admin: resolve CANCELAMENTO_EXECUCAO, resultado=CANCELADO)--> Cancelado [libera autorização]
Em Contestação --(admin: resolve CANCELAMENTO_EXECUCAO, resultado=APROVADO)--> Em Andamento [pedido de cancelamento negado]
Em Contestação --(sistema: timeout DISPUTE_MEDIATION_DAYS, CONTESTACAO_CONCLUSAO)--> Aprovado [auto, foundation/03-cancellation-rules.md]
Em Contestação --(sistema: timeout DISPUTE_MEDIATION_DAYS, CANCELAMENTO_EXECUCAO)--> Cancelado [auto]
Agendado --(cliente: cancela)--> Cancelado [Cenário B, foundation/03-cancellation-rules.md, multa + captura parcial]
Em Andamento --(cliente/profissional: solicita cancelamento)--> Em Contestação [Cenário C, PaymentDispute CANCELAMENTO_EXECUCAO]
```

- `Aprovado` gera `PaymentEvent` de captura **integral** (INV-041) e `Garantia` (INV-050). Não é o único gatilho de captura: `Cancelado` no Cenário B gera captura **parcial** da multa (INV-032, INV-041), nunca garantia.
- `Cancelado` nunca gera garantia nem libera pagamento **integral** do serviço (INV-032); captura/repasse da multa no Cenário B é a exceção explícita de INV-041 (`03-cancellation-rules.md`).
- `Agendado → Cancelado` aplica multa decrescente (10/25/50%) e captura parcial da `PaymentAuthorization` vigente; ver `foundation/03-cancellation-rules.md` e `04-modelo-dados.md`.
- Resolução de `Em Contestação` é manual por Admin no MVP, prazo `DISPUTE_MEDIATION_DAYS` (7d), com timeout automático por tipo de disputa (`03-cancellation-rules.md`).

## 4. Pagamento (`PaymentAuthorization` × `PaymentEvent`, duas máquinas, não uma)

> Corrigido em 2026-08-17 (3ª revisão do PO, `scripts/check-docs.sh`): a versão anterior misturava os dois no mesmo diagrama, tratando `Repassado`/`Reembolsado` como se fossem status de `PaymentAuthorization`. Não são, `StatusPaymentAuthorization` (`04-modelo-dados.md`) tem só 4 valores: `AUTORIZADO | CAPTURADO | CANCELADO | EXPIRADO`. `CAPTURADO` é terminal para a autorização em si (INV-042: toda autorização termina em captura/cancelamento/expiração). `Repassado`/`Reembolsado`/`Reautorizado` são **tipos de `PaymentEvent`**, registrados por cima de uma autorização já `CAPTURADO`, não mudam o `status` da autorização, só acrescentam histórico (INV-040, append-only).

**4a. Ciclo de vida de `PaymentAuthorization` (o `status`)**

```
Autorizado --(sistema: ServicoAprovado)--> Capturado [INV-041, P4]
Autorizado --(sistema: ServicoCancelado sem multa, Cenário B)--> Cancelado [libera 100%]
Autorizado --(sistema: ServicoCancelado com multa, Cenário B)--> Capturado [evento CAPTURADO parcial = valor_multa, foundation/03-cancellation-rules.md]
Autorizado --(sistema: ServicoCancelado, disputa CANCELAMENTO_EXECUCAO aceita)--> Cancelado [libera 100%, sem multa]
Autorizado --(sistema: expira sem uso)--> Expirado [INV-042, nunca fica em limbo]
Expirado --(sistema: Serviço ainda não Cancelado/Aprovado)--> nova PaymentAuthorization Autorizado [evento REAUTORIZADO, INV-046]
```

- Toda `PaymentAuthorization` termina em `Capturado`, `Cancelado` ou `Expirado`, nunca fica pendente indefinidamente (INV-042). `Capturado` é terminal: não existe transição de `status` para fora dele.
- Uma autorização que expira sem o Serviço ter chegado a `Aprovado`/`Cancelado` gera automaticamente uma **nova** `PaymentAuthorization` (linha própria, não mudança de status), cobre o caso de serviço agendado além da janela de autorização do gateway (cartão expira em ~5-7 dias; agendamento pode passar de 2 semanas). Nunca mais de uma `PaymentAuthorization` `Autorizado` por serviço ao mesmo tempo.
- Pix (`metodo = PIX`, `adr/ADR-005-gateway-pagamento.md`): a autorização **nasce** `Capturado` no aceite da proposta (captura imediata no Asaas). Não passa por `Autorizado`, INV-046 não dispara, `expira_em` é nulo. O diagrama acima é o fluxo de cartão.

**4b. Eventos registrados sobre uma autorização `Capturado` (o histórico, `PaymentEvent.tipo`)**

```
[PaymentAuthorization = Capturado] --(sistema: janela de contestação decorrida)--> evento REPASSADO [P6, AUTO_APPROVAL_HOURS = 72h]
[PaymentAuthorization = Capturado] --(cliente/admin: reembolso sobre valor já capturado)--> evento REEMBOLSADO [INV-043]
```

- Reembolso só existe sobre valor já capturado; sobre apenas autorizado, o evento correto é a transição de `status` para `Cancelado` em 4a, não um `PaymentEvent` de reembolso (INV-043).
- `REPASSADO` e `REEMBOLSADO` podem coexistir na mesma autorização (repasse ao profissional e reembolso parcial posterior ao cliente são eventos distintos, não mutuamente exclusivos), por isso vivem em `PaymentEvent`, não em `status`.

## 5. Garantia

```
(criada a partir de ServicoAprovado) --> Ativa [INV-050, prazo herdado da proposta, INV-051]
Ativa --(prazo expira sem acionamento)--> Expirada [nenhum evento financeiro, INV-053]
Ativa --(cliente: aciona com evidências)--> Acionada [INV-052]
Acionada --(mediação entre profissional e cliente, plataforma não é parte financeira)--> Encerrada [nenhum evento financeiro da plataforma, INV-053]
```

- Responsabilidade financeira é do profissional (Modelo B, decisão provisória de B001, `adr/ADR-003-garantia.md`); a plataforma media `Acionada → Encerrada` mas não gera `PaymentEvent`/`PaymentRefund` própria, o repasse ao profissional já ocorreu integralmente 72h após aprovação.
- Nenhum estado de `Garantia` toca `PaymentSplit` (mecanismo de reserva descartado em 2026-08-17, ver `adr/ADR-003-garantia.md`).

## 6. Conta

```
Pendente de Verificação --(admin: todos os documentos exigidos aprovados, RF002)--> Ativa [ProfissionalVerificado, INV-002]
Ativa --(admin: suspende)--> Suspensa [INV-003: cancela participação em processos abertos, preserva histórico]
Suspensa --(admin: reverte)--> Ativa
Ativa|Suspensa --(admin: bloqueia)--> Bloqueada
Ativa --(usuário: solicita exclusão)--> Excluída
```

- `Profissional` só participa de `SolicitacaoCriada → PropostaEnviada` enquanto `Ativa` (INV-002).
- Condições para `Pendente de Verificação → Ativa`: ver `04-modelo-dados.md` §DocumentoProfissional (todos os slots exigidos com `status = APROVADO`, sem pendências bloqueantes).

## 7. DocumentoProfissional

```
(upload) --> Pendente
Pendente --(admin: documento conforme critério RF002)--> Aprovado
Pendente --(admin: documento inválido)--> Rejeitado [motivo_rejeicao obrigatório]
Rejeitado --(profissional: reenvia mesmo tipo)--> nova linha Pendente [histórico preservado]
```

- Revisão **manual pelo Admin** no MVP (sem verificação automatizada).
- Para cada `tipo` exigido, o slot satisfeito é o registro **mais recente** com `Aprovado`.
- Quando o último slot pendente vira `Aprovado` e todos os exigidos estão cobertos, dispara transição `Conta: Pendente de Verificação → Ativa` (§6).

## Pendências deste documento

- Resolução de `Garantia: Acionada` é mediação entre profissional e cliente (Modelo B, `adr/ADR-003-garantia.md`, decisão provisória de B001), plataforma não participa financeiramente.
- Adapter concreto de captura parcial no gateway (Cenário B) depende de D1 (`05-arquitetura.md`).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | v0.1, criado a partir de `specifications/02-funcionalidades.md` §10 e `00-domain-invariants.md`. |
| 2026-08-17 | Adiciona transição `Expirado → Autorizado` (reautorização, INV-046), motivado por revisão do PO. |
| 2026-08-17 | 3ª revisão: separa §4 em duas máquinas (`PaymentAuthorization.status` vs. `PaymentEvent.tipo`), a versão anterior tratava `Repassado`/`Reembolsado` como status de autorização, quando são eventos sobre uma autorização já `Capturado` (terminal). |
| 2026-08-17 | §5 Garantia: adiciona gatilho de `RESERVA_LIBERADA` em `Expirada`/`Encerrada` (INV-053), garantia acionada agora tem lastro financeiro desenhado, mesmo com B001 (responsabilidade) ainda bloqueado. |
| 2026-08-17 | §5 Garantia revisada: decisão provisória de B001 remove o mecanismo de reserva, `Acionada`/`Encerrada`/`Expirada` não tocam mais `PaymentSplit`, responsabilidade financeira é do profissional, plataforma só media. |
| 2026-08-17 | §3 Serviço: prazo de aceite automático fixado em 72h (`AUTO_APPROVAL_HOURS`, `adr/ADR-004-prazo-aceite-automatico.md`), deixa de ser "prazo ainda não definido" (B002 resolvido). |
| 2026-08-17 | §3 Serviço: divide `Agendado|Em Andamento --cancela--> Cancelado` em dois, `Agendado` cancela direto (Cenário B), `Em Andamento` nunca cancela direto, abre `Em Contestação` (Cenário C), rascunho em `foundation/03-cancellation-rules.md` (B003, decisão parcial do PO). |
| 2026-08-17 | §4a: Pix nasce `Capturado` no aceite (`adr/ADR-005-gateway-pagamento.md`, B006); o diagrama de `Autorizado → Capturado` permanece o fluxo de cartão. |
| 2026-08-17 | §6 Conta: condição explícita de aprovação documental (RF002). §7 DocumentoProfissional: máquina `Pendente → Aprovado | Rejeitado`. |
| 2026-08-17 | B003 resolvido provisoriamente: transições de `Em Contestação` por tipo de disputa + timeout; §4a captura parcial Cenário B. |
