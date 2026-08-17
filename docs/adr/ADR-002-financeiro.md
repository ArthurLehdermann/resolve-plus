# ADR-002 — Modelo Financeiro (Escrow vs. Autorizar/Capturar)

**Status:** Decisão provisória de arquitetura (não é decisão jurídica/regulatória final)

**Data:** 2026-08-16 (PO)

## Contexto

`specifications/08-planejamento.md` listava como pergunta crítica em aberto: "o pagamento ficará em conta escrow ou será apenas autorizado e capturado depois?" (Grupo A — inconsistência editorial: outros trechos assumiam escrow implicitamente).

Escrow bancário verdadeiro exige instituição financeira parceira habilitada, compliance e fluxo regulatório próprio — complexidade alta para MVP. O usuário, de qualquer forma, deve perceber o pagamento como "protegido".

## Decisão

**Autorizar → Capturar → Repassar.** Não é escrow bancário.

```
Cliente contrata
      ↓
Pagamento autorizado
      ↓
Serviço executado
      ↓
Cliente aprova (ou janela de aceite automático expira — B002)
      ↓
Captura
      ↓
Repasse ao profissional (após janela de contestação — B002)
```

Do ponto de vista do usuário, a experiência continua sendo "pagamento protegido" (retido até aprovação). Operacionalmente é gateway padrão de autorização/captura, sem complexidade de instituição financeira própria.

## Consequências

- `00-domain-invariants.md` (INV-040 a INV-045) já modela pagamento como bounded context de eventos imutáveis, compatível com este modelo.
- Momento exato do repasse (janela de 48h/72h após aprovação) permanece bloqueador — ver `foundation/04-decisions-pending.md` (B002).
- Se o modelo de negócio evoluir para marketplace com maior exigência regulatória, este ADR pode ser revisto — não é decisão irreversível, é a mais simples que atende o MVP.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Decisão provisória do PO, registrada para encerrar a inconsistência "escrow assumido × pergunta aberta" (Grupo A). |
