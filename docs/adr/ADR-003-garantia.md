# ADR-003: Modelo de Responsabilidade da Garantia

**Status:** Decisão provisória de produto para permitir desenvolvimento (2026-08-17, PO). **Não é decisão jurídica final**, B001 continua bloqueado para o parecer definitivo, mas deixa de bloquear modelagem/implementação, ver `foundation/04-decisions-pending.md`.

**Data:** 2026-08-16 (PO), decisão provisória 2026-08-17

## Contexto

Responsabilidade pela garantia não é decisão de arquitetura, é decisão de direito do consumidor / risco de negócio. Existem três modelos possíveis:

- **Modelo A, Garantia do profissional.** Plataforma apenas conecta. Mais simples, mas expõe o cliente a risco caso o profissional não cumpra.
- **Modelo B, Garantia compartilhada.** Profissional executa e responde primeiro; plataforma media conflito.
- **Modelo C, Garantia da plataforma.** Plataforma assume o risco financeiro. Mais caro, aproxima a plataforma de uma seguradora.

## Decisão provisória (2026-08-17, PO), para permitir desenvolvimento

**Modelo B, sem retenção financeira adicional.**

```
Profissional executa
      ↓
Profissional responde pela garantia (sozinho, financeiramente)
      ↓
Plataforma apenas media conflito, não retém nem reembolsa
```

- A garantia é de responsabilidade do **profissional**.
- A plataforma atua como **mediadora** do conflito, não como parte financeiramente responsável.
- **Nenhuma retenção adicional** sobre o repasse do profissional além da comissão normal da plataforma (`PaymentSplit.valor_plataforma`, já cobrada em toda captura).

Reduz risco jurídico da plataforma, mantém a confiança percebida pelo cliente (existe processo de mediação), evita transformar a plataforma em seguradora de fato, e evita o problema regulatório descrito abaixo (retenção de fundo de terceiro).

## O que fica explicitamente fora do modelo, por ora

Não modelar enquanto B001 não tiver parecer jurídico definitivo:
- Fundo garantidor.
- Caução do profissional.
- Reserva financeira sobre o `PaymentSplit` (`valor_reserva_garantia`).
- Qualquer retenção percentual do repasse do profissional para cobrir garantia.

Se o parecer jurídico de B001 (`04-decisions-pending.md`) mudar essa decisão provisória no futuro, este ADR é revisado e os campos de reserva voltam para `specifications/04-modelo-dados.md` (`PaymentSplit`). Até lá, o mecanismo descrito nas revisões anteriores deste documento (retenção de `valor_reserva_garantia`) **não é implementado**.

## Por que este ADR não fecha a decisão definitivamente

Responsabilidade sobre garantia é matéria de direito do consumidor, exige validação jurídica antes de virar contrato ou termo de uso vinculante. A decisão provisória acima existe para que modelo de dados e fluxo de disputa (`foundation/02-state-machine.md` §5, transição `Garantia: Acionada → Encerrada`) tenham uma hipótese de trabalho **sem expor a plataforma a risco financeiro além da comissão**, o que reduz a urgência do parecer jurídico (não há dinheiro retido de terceiro em jogo) sem eliminá-la (responsabilidade legal por dano ao imóvel segue com parecer jurídico definitivo pendente, decisão provisória de B005 registrada em 2026-08-17).

## Consequências

- `Serviço`/`Garantia` no modelo de dados têm campo explícito indicando que a parte responsável é o profissional (Modelo B).
- Fluxo de disputa de garantia tem a plataforma como mediadora, não como parte financeiramente responsável primária, e não gera nenhum `PaymentEvent`/`PaymentRefund` da plataforma (o dinheiro já foi integralmente repassado ao profissional 72h após aprovação, `adr/ADR-004-prazo-aceite-automatico.md`).
- Termos de Uso precisam declarar isso explicitamente ao profissional no momento do cadastro/verificação: ele responde financeiramente pela garantia fora da plataforma, a plataforma só media.
- Resolve, por consequência, a reabertura de `ADR-002-financeiro.md` (ver changelog daquele documento): sem retenção do repasse do profissional, não há mais enquadramento de escrow pela garantia.

## Por que a versão anterior deste ADR (retenção sobre o repasse) foi descartada por ora

Registrado para não perder o raciocínio: reter uma fração do repasse ao profissional (dinheiro de terceiro) por prazo determinado após a captura (até o fim do prazo de garantia, pode passar de 90 dias) tem o mesmo enquadramento regulatório que `adr/ADR-002-financeiro.md` rejeitou ao descartar escrow bancário, com prazo de retenção maior que o escrow original teria. Além disso, retenção parcial com liberação posterior sobre uma transação já capturada é um produto de gateway separado, nem sempre disponível (Asaas e Pagar.me fazem split no momento da captura). Em vez de resolver isso com parecer jurídico antes de poder desenvolver, o PO optou por remover a retenção do desenho provisório e reavaliar apenas se/quando o parecer jurídico de B001 exigir um mecanismo de lastro financeiro.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Recomendação registrada pelo PO como arquiteto responsável, pendente de parecer jurídico (B001). |
| 2026-08-17 | Adiciona mecanismo financeiro de reserva (INV-053), desenhado antes do parecer jurídico, como pedido pelo PO. |
| 2026-08-17 | Reaberto na mesma data: mecanismo de reserva pode reintroduzir escrow, contradiz `ADR-002-financeiro.md`. Funde a pergunta de percentual em B001 (`04-decisions-pending.md`), registra alternativa (lastrear com comissão da plataforma em vez do repasse do profissional). |
| 2026-08-17 | Quinta revisão do PO: decisão provisória para destravar desenvolvimento. Garantia é do profissional, plataforma só media, **sem** retenção financeira adicional. Mecanismo de reserva removido do desenho ativo (fica registrado como histórico), campos de reserva removidos de `specifications/04-modelo-dados.md`. B001 permanece bloqueado apenas para o parecer jurídico definitivo, não bloqueia mais o desenvolvimento. |
