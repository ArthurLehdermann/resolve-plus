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

**Desenho**: uma fração do `valor_profissional` no `PaymentSplit` (INV-044) fica retida como `valor_reserva_garantia` até a `Garantia` do serviço sair de `ATIVA` (INV-053, `04-modelo-dados.md`, `02-state-machine.md` §5). Se a garantia expira sem acionamento, a reserva é liberada integralmente ao profissional (`RESERVA_LIBERADA`). Se é acionada e a resolução envolve reembolso ao cliente, o reembolso é **limitado ao valor da reserva**, dano acima disso não é coberto por este mecanismo, a plataforma continua não sendo seguradora (ver B005, responsabilidade civil por dano ao imóvel, para o que fica fora do teto).

Este desenho depende de B001 fechar, e agora inclui o parecer sobre reter repasse do profissional (ver seção abaixo, reaberto em 2026-08-17). O percentual retido, se confirmado, também está dentro de B001 (`04-decisions-pending.md`), ainda sem valor definido.

**O que este mecanismo não resolve**: se o dano exceder a reserva, ou se o profissional simplesmente não tiver mais conta ativa na plataforma para reter contra, o mecanismo não cobre a diferença. Isso é o próprio limite do Modelo B (plataforma medeia, não segura), se o PO validar juridicamente que a plataforma precisa cobrir mais que isso, o percentual de reserva sobe, ou o modelo muda para C.

## Reaberto em 2026-08-17, INV-053 pode reintroduzir escrow pela porta dos fundos

Quarta revisão crítica do PO: reter uma fração do repasse ao profissional (dinheiro de terceiro) por prazo determinado após a captura (até o fim do prazo de garantia, pode passar de 90 dias) tem o mesmo enquadramento regulatório que `adr/ADR-002-financeiro.md` rejeitou ao descartar escrow bancário, com prazo de retenção maior que o escrow original teria. Além disso, retenção parcial com liberação posterior sobre uma transação já capturada é um produto de gateway separado, nem sempre disponível (Asaas e Pagar.me fazem split no momento da captura; `ADR-002-financeiro.md` lista reautorização como pendência de gateway, mas não lista isto).

**O mecanismo em si está certo no domínio** (garantia precisa de lastro financeiro, INV-053). **O problema é que ele muda a resposta de `ADR-002-financeiro.md` e esse ADR não foi reaberto até agora.** Os dois ADRs (`002` e `003`) e B001 (`04-decisions-pending.md`) viram um parecer jurídico único, não é possível decidir responsabilidade da garantia sem decidir também se o mecanismo que a financia é viável.

**Alternativa a avaliar, se reter o repasse do profissional não passar no parecer**: lastrear a garantia com uma fração da comissão da própria plataforma (`PaymentSplit.valor_plataforma`), que já é dela por direito, não é retenção de fundo de terceiro. Reduz a margem da plataforma em vez de atrasar o pagamento do profissional. Não avaliado em detalhe, registrado aqui como caminho alternativo para quando isso voltar à mesa.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Recomendação registrada pelo PO como arquiteto responsável, pendente de parecer jurídico (B001). |
| 2026-08-17 | Adiciona mecanismo financeiro de reserva (INV-053), desenhado antes do parecer jurídico, como pedido pelo PO. |
| 2026-08-17 | Reaberto na mesma data: mecanismo de reserva pode reintroduzir escrow, contradiz `ADR-002-financeiro.md`. Funde a pergunta de percentual em B001 (`04-decisions-pending.md`), registra alternativa (lastrear com comissão da plataforma em vez do repasse do profissional). |
