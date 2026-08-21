# ADR-002: Modelo Financeiro (Escrow vs. Autorizar/Capturar)

**Status:** Decisão provisória de arquitetura (não é decisão jurídica/regulatória final). Foi **reaberto em 2026-08-17** por causa da reserva de garantia proposta em `ADR-003-garantia.md`, e **fechado novamente na mesma data**: a decisão provisória de B001 (`04-decisions-pending.md`) removeu a retenção sobre o repasse do profissional, então o problema que reabriu este ADR deixou de existir. Ver seção "Reaberto e fechado em 2026-08-17" abaixo.

**Data:** 2026-08-16 (PO), reaberto e fechado novamente em 2026-08-17

## Contexto

`specifications/08-planejamento.md` listava como pergunta crítica em aberto: "o pagamento ficará em conta escrow ou será apenas autorizado e capturado depois?" (Grupo A, inconsistência editorial: outros trechos assumiam escrow implicitamente).

Escrow bancário verdadeiro exige instituição financeira parceira habilitada, compliance e fluxo regulatório próprio, complexidade alta para MVP. O usuário, de qualquer forma, deve perceber o pagamento como "protegido".

## Decisão

**Autorizar → Capturar → Repassar.** Não é escrow bancário.

```
Cliente contrata
      ↓
Pagamento autorizado
      ↓
Serviço executado
      ↓
Cliente aprova (ou janela de aceite automático expira, B002)
      ↓
Captura
      ↓
Repasse ao profissional (após janela de contestação, B002)
```

Do ponto de vista do usuário, a experiência continua sendo "pagamento protegido" (retido até aprovação). Operacionalmente é gateway padrão de autorização/captura, sem complexidade de instituição financeira própria.

## Consequências

- `00-domain-invariants.md` (INV-040 a INV-045) já modela pagamento como bounded context de eventos imutáveis, compatível com este modelo.
- Momento exato do repasse é a janela de 72h após aprovação (`AUTO_APPROVAL_HOURS`), decidido em `adr/ADR-004-prazo-aceite-automatico.md` (B002, `foundation/04-decisions-pending.md`, resolvido).
- Gateway, reautorização, split nativo e Pix no MVP: `adr/ADR-005-gateway-pagamento.md` (B006).
- Se o modelo de negócio evoluir para marketplace com maior exigência regulatória, este ADR pode ser revisto, não é decisão irreversível, é a mais simples que atende o MVP.

## Pendências não tratadas por esta decisão (identificadas em 2026-08-17)

> Resolvidas em 2026-08-17 por `adr/ADR-005-gateway-pagamento.md` (B006). Esta decisão sozinha não escolhia provedor nem Pix; o ADR-005 fecha as três pontas abaixo.

- **Pix não tem autorizar/capturar.** Aceito no MVP nascendo `PENDENTE` até o webhook do Asaas confirmar (INV-047, corrigido em 2026-08-20; a decisão original aqui previa captura imediata), com retenção no Asaas (IP autorizada pelo BCB) até o evento `REPASSADO`. Cartão permanece autorizar → capturar → repassar. Não é escrow bancário em conta própria da plataforma.
- **Expiração de autorização de cartão** (INV-046): Asaas cobre com nova cobrança `authorizeOnly` no `creditCardToken` (sem CVV na reautorização). Janela configurável de 3 a 25 dias. Gateway deixa de ser "Necessita Validação" em `05-arquitetura.md`.
- **Split nativo na captura** (INV-044): Asaas `splits` na captura de cartão. Pix calcula `PaymentSplit` no `CAPTURADO` e move o dinheiro no `REPASSADO` via transferência interna (`walletId`).

## Reaberto e fechado em 2026-08-17: a garantia (INV-053) quase reintroduziu escrow

Este ADR rejeitou escrow bancário especificamente para não reter dinheiro de terceiro. `ADR-003-garantia.md` chegou a propor financiar a garantia retendo uma fração do repasse ao **profissional** (que também é terceiro em relação à plataforma) por prazo determinado após a captura, até 90 dias. Segurar recurso de terceiro por prazo determinado teria o mesmo enquadramento de conta de pagamento que este ADR contornou, reintroduzido por outra porta, com prazo maior do que o escrow original teria.

**Fechado na mesma data**: o PO decidiu, provisoriamente (B001, `04-decisions-pending.md`), que a garantia é de responsabilidade do profissional e a plataforma não retém nenhuma fração adicional do repasse. Sem retenção, não há reintrodução de escrow, o problema descrito acima deixa de existir. `INV-053` foi reescrita em `00-domain-invariants.md` para refletir isso. Se o parecer jurídico definitivo de B001 mudar essa decisão, este ADR volta a ser reaberto.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Decisão provisória do PO, registrada para encerrar a inconsistência "escrow assumido × pergunta aberta" (Grupo A). |
| 2026-08-17 | Adiciona seção de pendências não tratadas (Pix, reautorização, split nativo), identificadas em revisão crítica do PO. |
| 2026-08-17 | Reaberto: `INV-053` (reserva de garantia sobre repasse do profissional) tem o mesmo enquadramento regulatório que este ADR rejeitou. Fundido com B001. |
| 2026-08-17 | Fechado novamente na mesma data: decisão provisória de B001 remove a retenção sobre o repasse do profissional (`ADR-003-garantia.md`), o enquadramento de escrow deixa de se aplicar. |
| 2026-08-17 | Pendências residuais (Pix, reautorização, split nativo) resolvidas por `adr/ADR-005-gateway-pagamento.md` (B006): Asaas no MVP, Pix aceito com captura imediata. |
| 2026-08-20 | Corrige "Pix aceito com captura imediata": a implementação mostrou que o Asaas não confirma a cobrança Pix no ato, só via webhook. Pix nasce `PENDENTE`, não `CAPTURADO` (INV-047, `adr/ADR-005-gateway-pagamento.md`). |
