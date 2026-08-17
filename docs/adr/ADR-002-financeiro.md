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
- Se o modelo de negócio evoluir para marketplace com maior exigência regulatória, este ADR pode ser revisto, não é decisão irreversível, é a mais simples que atende o MVP.

## Pendências não tratadas por esta decisão (identificadas em 2026-08-17)

- **Pix não tem autorizar/capturar.** Pix é o método dominante no Brasil. Aceitar Pix neste modelo implica captura imediata com saldo retido pela plataforma até o repasse, que é escrow de fato, com possível enquadramento como conta de pagamento pelo BCB. Este ADR não avalia essa implicação. Se Pix for aceito como método no MVP, revisar antes de implementar.
- **Expiração de autorização de cartão** (~5-7 dias) vs. janela de serviço agendado (pode passar de 2 semanas): tratado no modelo de dados via `INV-046`/evento `REAUTORIZADO` (`04-modelo-dados.md`, 2026-08-17), mas ainda depende do gateway escolhido suportar reautorização, não confirmado, gateway segue "Necessita Validação" em `05-arquitetura.md`.
- **Split de comissão no momento da captura** (INV-044) exige gateway com split nativo. Amarra a escolha de gateway a essa capacidade, ainda não validada.

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
