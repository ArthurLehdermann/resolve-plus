# ADR-003: Modelo de Responsabilidade da Garantia

**Status:** Recomendação registrada, **não é decisão final**, depende de parecer jurídico (ver `foundation/04-decisions-pending.md`, B001)

**Data:** 2026-08-16 (PO)

## Contexto

Responsabilidade pela garantia não é decisão de arquitetura, é decisão de direito do consumidor / risco de negócio. Existem três modelos possíveis:

- **Modelo A, Garantia do profissional.** Plataforma apenas conecta. Mais simples, mas expõe o cliente a risco caso o profissional não cumpra.
- **Modelo B, Garantia compartilhada.** Profissional executa e responde primeiro; plataforma media conflito.
- **Modelo C, Garantia da plataforma.** Plataforma assume o risco financeiro. Mais caro, aproxima a plataforma de uma seguradora.

## Recomendação (não decisão)

**Modelo B.**

```
Profissional executa
      ↓
Profissional responde pela garantia
      ↓
Plataforma apenas media conflito
```

Reduz risco jurídico da plataforma, mantém a confiança percebida pelo cliente, evita transformar a plataforma em seguradora de fato.

## Por que este ADR não fecha a decisão

Responsabilidade sobre garantia é matéria de direito do consumidor, exige validação jurídica antes de virar contrato ou termo de uso vinculante. Este documento registra a recomendação arquitetural/de produto para que modelo de dados e fluxo de disputa (`foundation/02-state-machine.md`, transição `Garantia: Acionada → Encerrada`) tenham uma hipótese de trabalho, não para autorizar implementação de responsabilidade legal sem revisão.

## Consequências se confirmado

- `Property`/`Service`/`Warranty` no modelo de dados precisam de campo explícito de parte responsável.
- Fluxo de disputa de garantia tem a plataforma como mediadora, não como parte financeiramente responsável primária.
- Termos de Uso precisam declarar isso explicitamente ao profissional no momento do cadastro/verificação.

## Mecanismo financeiro (desenhado em 2026-08-17, independente do parecer jurídico de B001)

Identificado em terceira revisão crítica do PO: mesmo com Modelo B recomendado, garantia acionada não tinha nenhum lastro financeiro. O repasse ao profissional ocorre ~72h após aprovação (B002); a garantia pode ser acionada até o fim do prazo de garantia (pode passar de 90 dias). Sem retenção, "profissional responde pela garantia" (Modelo B) não tem como ser cobrado na prática, o dinheiro já saiu.

**Desenho**: uma fração do `valor_profissional` no `PaymentSplit` (INV-044) fica retida como `valor_reserva_garantia` até a `Garantia` do serviço sair de `ATIVA` (INV-053, `04-modelo-dados.md`, `02-state-machine.md` §5). Se a garantia expira sem acionamento, a reserva é liberada integralmente ao profissional (`RESERVA_LIBERADA`). Se é acionada e a resolução envolve reembolso ao cliente, o reembolso é **limitado ao valor da reserva** — dano acima disso não é coberto por este mecanismo, a plataforma continua não sendo seguradora (ver B005, responsabilidade civil por dano ao imóvel, para o que fica fora do teto).

Este desenho **não depende de B001 fechar** para existir, ele só passa a ser executado de fato quando B001 definir quem decide o desfecho de `Acionada → Encerrada`. O percentual retido é B006 (`04-decisions-pending.md`), ainda sem valor definido.

**O que este mecanismo não resolve**: se o dano exceder a reserva, ou se o profissional simplesmente não tiver mais conta ativa na plataforma para reter contra, o mecanismo não cobre a diferença. Isso é o próprio limite do Modelo B (plataforma medeia, não segura) — se o PO validar juridicamente que a plataforma precisa cobrir mais que isso, o percentual de reserva (B006) sobe, ou o modelo muda para C.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Recomendação registrada pelo PO como arquiteto responsável, pendente de parecer jurídico (B001). |
| 2026-08-17 | Adiciona mecanismo financeiro de reserva (INV-053, B006) — desenhado antes do parecer jurídico, como pedido pelo PO. |
