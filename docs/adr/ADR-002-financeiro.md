# ADR-002: Modelo Financeiro (Escrow vs. Autorizar/Capturar)

**Status:** Decisão provisória de arquitetura (não é decisão jurídica/regulatória final). **Reaberto em 2026-08-17**, ver seção dedicada abaixo. A decisão original (Autorizar → Capturar → Repassar) continua valendo para o fluxo principal; o que está em xeque é se `INV-053`/`ADR-003-garantia.md` (reserva de garantia) reintroduz, por outro caminho, exatamente o que este ADR tentou evitar.

**Data:** 2026-08-16 (PO), reaberto 2026-08-17

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
- Momento exato do repasse (janela de 48h/72h após aprovação) permanece bloqueador, ver `foundation/04-decisions-pending.md` (B002).
- Se o modelo de negócio evoluir para marketplace com maior exigência regulatória, este ADR pode ser revisto, não é decisão irreversível, é a mais simples que atende o MVP.

## Pendências não tratadas por esta decisão (identificadas em 2026-08-17)

- **Pix não tem autorizar/capturar.** Pix é o método dominante no Brasil. Aceitar Pix neste modelo implica captura imediata com saldo retido pela plataforma até o repasse, que é escrow de fato, com possível enquadramento como conta de pagamento pelo BCB. Este ADR não avalia essa implicação. Se Pix for aceito como método no MVP, revisar antes de implementar.
- **Expiração de autorização de cartão** (~5-7 dias) vs. janela de serviço agendado (pode passar de 2 semanas): tratado no modelo de dados via `INV-046`/evento `REAUTORIZADO` (`04-modelo-dados.md`, 2026-08-17), mas ainda depende do gateway escolhido suportar reautorização, não confirmado, gateway segue "Necessita Validação" em `05-arquitetura.md`.
- **Split de comissão no momento da captura** (INV-044) exige gateway com split nativo. Amarra a escolha de gateway a essa capacidade, ainda não validada.

## Reaberto em 2026-08-17: a garantia (INV-053) pode reintroduzir escrow

Este ADR rejeitou escrow bancário especificamente para não reter dinheiro de terceiro. `ADR-003-garantia.md` propôs financiar a garantia retendo uma fração do repasse ao **profissional** (que também é terceiro em relação à plataforma) por prazo determinado após a captura, até 90 dias. Segurar recurso de terceiro por prazo determinado é o enquadramento de conta de pagamento, exatamente o que este ADR contornou, reintroduzido por outra porta, com prazo maior do que o escrow original teria (o escrow discutido originalmente segurava o dinheiro do **cliente** até a aprovação do serviço, uma janela de dias; a reserva de garantia segura o dinheiro do **profissional** por até o prazo de garantia inteiro).

Consequência prática: `INV-053` não é mais tratada como invariante fechada (ver `00-domain-invariants.md`) até este ponto ser resolvido. B001 (`04-decisions-pending.md`) foi ampliado para cobrir responsabilidade da garantia **e** viabilidade regulatória de retê-la sobre o repasse do profissional, como um parecer jurídico único, não dois separados.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Decisão provisória do PO, registrada para encerrar a inconsistência "escrow assumido × pergunta aberta" (Grupo A). |
| 2026-08-17 | Adiciona seção de pendências não tratadas (Pix, reautorização, split nativo), identificadas em revisão crítica do PO. |
| 2026-08-17 | Reaberto: `INV-053` (reserva de garantia sobre repasse do profissional) tem o mesmo enquadramento regulatório que este ADR rejeitou. Fundido com B001. |
