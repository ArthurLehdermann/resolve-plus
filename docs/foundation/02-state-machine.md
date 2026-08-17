# 02, State Machine

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

## 4. Pagamento (`PaymentAuthorization` → eventos)

```
Autorizado --(sistema: ServicoAprovado)--> Capturado [INV-041, P4]
Autorizado --(sistema: ServicoCancelado antes de aprovação)--> Cancelado [nunca captura sobre serviço cancelado]
Autorizado --(sistema: expira sem uso)--> Expirado [INV-042, nunca fica em limbo]
Expirado --(sistema: Serviço ainda não Cancelado/Aprovado)--> nova PaymentAuthorization Autorizado [REAUTORIZADO, INV-046]
Capturado --(sistema: janela de contestação decorrida)--> Repassado [P6, prazo B002]
Capturado --(cliente/admin: reembolso, sobre valor já capturado)--> Reembolsado [INV-043]
```

- Toda `PaymentAuthorization` tem que terminar em `Capturado`, `Cancelado` ou `Expirado`, nunca fica pendente indefinidamente (INV-042).
- Uma autorização que expira sem o Serviço ter chegado a `Aprovado`/`Cancelado` gera automaticamente uma nova `PaymentAuthorization` (INV-046), cobre o caso de serviço agendado além da janela de autorização do gateway (cartão expira em ~5-7 dias; agendamento pode passar de 2 semanas). Nunca mais de uma `PaymentAuthorization` `Autorizado` por serviço ao mesmo tempo.
- Reembolso só existe sobre capturado; sobre apenas autorizado, o correto é `Cancelado` (INV-043).

## 5. Garantia

```
(criada a partir de ServicoAprovado) --> Ativa [INV-050, prazo herdado da proposta, INV-051]
Ativa --(prazo expira sem acionamento)--> Expirada
Ativa --(cliente: aciona com evidências)--> Acionada [INV-052]
Acionada --(resolução, responsável pendente de B001)--> Encerrada
```

- Responsabilidade sobre quem resolve `Acionada → Encerrada` depende de B001 (garantia do profissional, da plataforma, ou compartilhada).

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
