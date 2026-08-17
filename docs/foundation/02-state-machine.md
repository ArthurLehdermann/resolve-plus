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
Aguardando Aprovação --(sistema: janela de aceite automático expira sem contestação)--> Aprovado [B002, prazo ainda não definido]
Aguardando Aprovação --(cliente: contesta)--> Em Contestação [FA004]
Em Contestação --(fluxo de mediação, NECESSITA VALIDAÇÃO, ver B003)--> Aprovado | Cancelado
Agendado|Em Andamento --(cliente/profissional/admin: cancela)--> Cancelado [regras de quem/até quando/multa pendentes, B003]
```

- `Aprovado` é o único estado que pode gerar `PaymentEvent` de captura (INV-041) e `Garantia` (INV-050).
- `Cancelado` nunca gera garantia nem libera pagamento normal (INV-032).
- Transição `Em Contestação → ?` está **bloqueada** até B003 ser resolvido, hoje não há resolução determinística no domínio.

## 4. Pagamento (`PaymentAuthorization` × `PaymentEvent`, duas máquinas, não uma)

> Corrigido em 2026-08-17 (3ª revisão do PO, `scripts/check-docs.sh`): a versão anterior misturava os dois no mesmo diagrama, tratando `Repassado`/`Reembolsado` como se fossem status de `PaymentAuthorization`. Não são, `StatusPaymentAuthorization` (`04-modelo-dados.md`) tem só 4 valores: `AUTORIZADO | CAPTURADO | CANCELADO | EXPIRADO`. `CAPTURADO` é terminal para a autorização em si (INV-042: toda autorização termina em captura/cancelamento/expiração). `Repassado`/`Reembolsado`/`Reautorizado` são **tipos de `PaymentEvent`**, registrados por cima de uma autorização já `CAPTURADO`, não mudam o `status` da autorização, só acrescentam histórico (INV-040, append-only).

**4a. Ciclo de vida de `PaymentAuthorization` (o `status`)**

```
Autorizado --(sistema: ServicoAprovado)--> Capturado [INV-041, P4]
Autorizado --(sistema: ServicoCancelado antes de aprovação)--> Cancelado [nunca captura sobre serviço cancelado]
Autorizado --(sistema: expira sem uso)--> Expirado [INV-042, nunca fica em limbo]
Expirado --(sistema: Serviço ainda não Cancelado/Aprovado)--> nova PaymentAuthorization Autorizado [evento REAUTORIZADO, INV-046]
```

- Toda `PaymentAuthorization` termina em `Capturado`, `Cancelado` ou `Expirado`, nunca fica pendente indefinidamente (INV-042). `Capturado` é terminal: não existe transição de `status` para fora dele.
- Uma autorização que expira sem o Serviço ter chegado a `Aprovado`/`Cancelado` gera automaticamente uma **nova** `PaymentAuthorization` (linha própria, não mudança de status), cobre o caso de serviço agendado além da janela de autorização do gateway (cartão expira em ~5-7 dias; agendamento pode passar de 2 semanas). Nunca mais de uma `PaymentAuthorization` `Autorizado` por serviço ao mesmo tempo.

**4b. Eventos registrados sobre uma autorização `Capturado` (o histórico, `PaymentEvent.tipo`)**

```
[PaymentAuthorization = Capturado] --(sistema: janela de contestação decorrida)--> evento REPASSADO [P6, prazo B002]
[PaymentAuthorization = Capturado] --(cliente/admin: reembolso sobre valor já capturado)--> evento REEMBOLSADO [INV-043]
```

- Reembolso só existe sobre valor já capturado; sobre apenas autorizado, o evento correto é a transição de `status` para `Cancelado` em 4a, não um `PaymentEvent` de reembolso (INV-043).
- `REPASSADO` e `REEMBOLSADO` podem coexistir na mesma autorização (repasse ao profissional e reembolso parcial posterior ao cliente são eventos distintos, não mutuamente exclusivos), por isso vivem em `PaymentEvent`, não em `status`.

## 5. Garantia

```
(criada a partir de ServicoAprovado) --> Ativa [INV-050, prazo herdado da proposta, INV-051]
Ativa --(prazo expira sem acionamento)--> Expirada [dispara evento RESERVA_LIBERADA sobre PaymentSplit, INV-053]
Ativa --(cliente: aciona com evidências)--> Acionada [INV-052]
Acionada --(resolução, responsável pendente de B001)--> Encerrada [dispara evento RESERVA_LIBERADA ou PaymentRefund limitado a valor_reserva_garantia, INV-053]
```

- Responsabilidade sobre quem resolve `Acionada → Encerrada` depende de B001 (garantia do profissional, da plataforma, ou compartilhada), mas o mecanismo financeiro que sustenta a resolução (reserva sobre o split) não depende de B001 fechar, está desenhado em INV-053 desde 2026-08-17.
- `Expirada` e `Encerrada` liberam a reserva (total ou o que sobrar dela); nenhum outro estado de `Garantia` toca `PaymentSplit`.

## 6. Conta

```
Pendente de Verificação --(sistema/admin: aprova documentos)--> Ativa
Ativa --(admin: suspende)--> Suspensa [INV-003: cancela participação em processos abertos, preserva histórico]
Suspensa --(admin: reverte)--> Ativa
Ativa|Suspensa --(admin: bloqueia)--> Bloqueada
Ativa --(usuário: solicita exclusão)--> Excluída
```

- `Profissional` só participa de `SolicitacaoCriada → PropostaEnviada` enquanto `Ativa` (INV-002).

## Pendências deste documento

- Transição de `Em Contestação` no Serviço não é determinística (depende de B003).
- Prazo exato da janela de aceite automático/contestação (B002) não está fixado, hoje é parâmetro simbólico.
- Resolução de `Garantia: Acionada` depende de B001.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | v0.1, criado a partir de `specifications/02-funcionalidades.md` §10 e `00-domain-invariants.md`. |
| 2026-08-17 | Adiciona transição `Expirado → Autorizado` (reautorização, INV-046), motivado por revisão do PO. |
| 2026-08-17 | 3ª revisão: separa §4 em duas máquinas (`PaymentAuthorization.status` vs. `PaymentEvent.tipo`), a versão anterior tratava `Repassado`/`Reembolsado` como status de autorização, quando são eventos sobre uma autorização já `Capturado` (terminal). |
| 2026-08-17 | §5 Garantia: adiciona gatilho de `RESERVA_LIBERADA` em `Expirada`/`Encerrada` (INV-053), garantia acionada agora tem lastro financeiro desenhado, mesmo com B001 (responsabilidade) ainda bloqueado. |
