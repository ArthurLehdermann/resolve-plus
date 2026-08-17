# ADR-003 — Modelo de Responsabilidade da Garantia

**Status:** Recomendação registrada, **não é decisão final** — depende de parecer jurídico (ver `foundation/04-decisions-pending.md`, B001)

**Data:** 2026-08-16 (PO)

## Contexto

Responsabilidade pela garantia não é decisão de arquitetura, é decisão de direito do consumidor / risco de negócio. Existem três modelos possíveis:

- **Modelo A — Garantia do profissional.** Plataforma apenas conecta. Mais simples, mas expõe o cliente a risco caso o profissional não cumpra.
- **Modelo B — Garantia compartilhada.** Profissional executa e responde primeiro; plataforma media conflito.
- **Modelo C — Garantia da plataforma.** Plataforma assume o risco financeiro. Mais caro, aproxima a plataforma de uma seguradora.

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

Responsabilidade sobre garantia é matéria de direito do consumidor — exige validação jurídica antes de virar contrato ou termo de uso vinculante. Este documento registra a recomendação arquitetural/de produto para que modelo de dados e fluxo de disputa (`foundation/02-state-machine.md`, transição `Garantia: Acionada → Encerrada`) tenham uma hipótese de trabalho, não para autorizar implementação de responsabilidade legal sem revisão.

## Consequências se confirmado

- `Property`/`Service`/`Warranty` no modelo de dados precisam de campo explícito de parte responsável.
- Fluxo de disputa de garantia tem a plataforma como mediadora, não como parte financeiramente responsável primária.
- Termos de Uso precisam declarar isso explicitamente ao profissional no momento do cadastro/verificação.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Recomendação registrada pelo PO como arquiteto responsável, pendente de parecer jurídico (B001). |
