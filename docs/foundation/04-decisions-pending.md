# 04: Decisions Pending (Bloqueadores Formais)

> Bloqueadores de arquitetura/negócio, com status explícito. Nenhum item aqui é "detalhe a resolver depois", são pontos que **mudam** modelo de dados, API ou fluxo financeiro dependendo da resposta. Registrado pelo PO em 2026-08-16.

## B001, Responsabilidade da Garantia (inclui o mecanismo financeiro que a lastreia)

**Status:** Parcialmente resolvido. Decisão provisória de produto registrada em 2026-08-17 (quinta revisão do PO) para **destravar desenvolvimento**, mas o parecer jurídico definitivo continua bloqueado, esta decisão pode mudar.

**Impacta:** Modelo de Dados · APIs · Fluxo Financeiro · Termos de Uso · Enquadramento Regulatório

**Decisão provisória do PO (2026-08-17):**
- A garantia é de responsabilidade do **profissional** (Modelo A/B, ver `adr/ADR-003-garantia.md`).
- A plataforma atua como **mediadora**, não como parte financeiramente responsável.
- **Nenhuma retenção adicional** sobre o repasse do profissional além da comissão normal da plataforma.
- **Não modelar ainda**: fundo garantidor, caução, reserva financeira, retenção percentual para cobrir garantia. Esses itens alteram o contexto financeiro e continuam exigindo validação jurídica/regulatória antes de qualquer implementação.

Isso resolve, por consequência, a reabertura de `adr/ADR-002-financeiro.md`: sem retenção do repasse do profissional, o enquadramento de escrow que motivou a reabertura deixa de se aplicar (ver changelog daquele ADR).

**O que continua bloqueado (parecer jurídico definitivo):** se essa decisão provisória (sem retenção) é juridicamente suficiente para sustentar "plataforma media, profissional responde", ou se algum mecanismo de lastro financeiro (reserva, caução, fundo) acaba sendo exigido mais adiante. Se o parecer mudar a decisão provisória, `adr/ADR-003-garantia.md`, `adr/ADR-002-financeiro.md`, `adr/ADR-005-gateway-pagamento.md`, `00-domain-invariants.md` (INV-053) e `specifications/04-modelo-dados.md` (`PaymentSplit`) precisam ser revisados juntos, mesma dependência de antes.

**Responsável:** Jurídico + Produto + Financeiro (parecer definitivo). Decisão provisória já registrada pelo PO.

---

## B002, Prazo para Aceite Automático / Repasse

**Status:** Resolvido provisoriamente (2026-08-17, decisão de Produto do PO). Registrado como `ADR-004-prazo-aceite-automatico.md`, decisão de produto, não é bloqueador jurídico, pode ser alterada por configuração sem migration.

**Impacta:** State Machine · Fluxo Financeiro · Pagamento · Notificações

**Decisão:** 72 horas, modelado como parâmetro `AUTO_APPROVAL_HOURS` (tabela `Configuração`, `04-modelo-dados.md`), não como valor fixo em código. Ver `adr/ADR-004-prazo-aceite-automatico.md`.

Relacionado a `INV-031` e `INV-041` (`00-domain-invariants.md`): o pagamento só é **repassado** após aprovação do cliente **ou** o esgotamento desta janela sem contestação. Captura de Pix é imediata no aceite (`adr/ADR-005-gateway-pagamento.md`); captura de cartão continua após aprovação.

**Responsável:** Produto (decidido). Fica aberto apenas o ajuste fino do valor após dado real de uso, não bloqueia desenvolvimento.

---

## B003, Cancelamento

**Status:** Em elaboração (2026-08-17, quinta revisão do PO). Rascunho de regras registrado em `foundation/03-cancellation-rules.md`, com os 4 cenários (antes de proposta, após proposta aceita, durante execução, após conclusão) mapeados para os estados de `02-state-machine.md`. **Não desbloqueia o fluxo principal**: percentual de multa e mecanismo de resolução de disputa continuam sem resposta.

**Definido no rascunho:**
- Antes de proposta aceita: cliente cancela livremente, sem custo (nada foi cobrado ainda).
- Após proposta aceita, antes de iniciar (`Agendado`): cliente cancela, pode gerar multa (percentual em aberto).
- Durante execução (`Em Andamento`): nunca cancela direto, sempre abre disputa (`Em Contestação`).
- Após conclusão (`Aprovado`): não existe cancelamento, existe garantia (INV-053/B001) ou, se ainda dentro da janela de aceite automático, contestação de conclusão (já existente, FA004).

**Ainda falta definir** (`foundation/03-cancellation-rules.md`, seção "O que fica pendente"):
- Percentual de multa (Cenário B).
- Mecânica de captura parcial (cartão ainda `AUTORIZADO`) / reembolso parcial (Pix já `CAPTURADO`) no Cenário B. Gateway é Asaas (`adr/ADR-005-gateway-pagamento.md`); captura parcial continua **não** assumida.
- Resolução determinística de `Em Contestação → Aprovado | Cancelado` (Cenários C/D).
- Impacto de cancelamento/contestação recorrente na reputação.

**Responsável:** Produto + Jurídico.

---

## B004, Histórico Manual do Imóvel

**Status:** Bloqueado (decisão provisória do PO já registrada, falta validação)

**Alternativas avaliadas:**
- **A, Somente registros gerados pela plataforma.** Prós: dados confiáveis. Contras: histórico incompleto.
- **B, Permitir registros manuais.** Prós: aumenta o valor do prontuário. Contras: reduz confiabilidade.

**Decisão provisória do PO (2026-08-16): modelo híbrido.** Todo registro tem uma `origem` (`PLATAFORMA | MANUAL | IMPORTADO`) com selo de confiabilidade próprio, um comprador futuro do imóvel distingue manutenção comprovada por serviço contratado na plataforma de anotação manual do proprietário. Refletido em `00-domain-invariants.md` (INV-062).

**Responsável:** Produto (validar antes de fechar `specifications/04-modelo-dados.md`).

## B005, Responsabilidade Civil por Dano ao Imóvel

**Status:** Bloqueado

**Impacta:** Termos de Uso · Fluxo Financeiro · Verificação de Profissional (RF002)

Nenhum documento até 2026-08-17 tratava de quem responde quando um profissional causa dano ao imóvel durante a execução (ex.: vazamento em serviço hidráulico, dano elétrico). É o maior risco reputacional de um marketplace presencial e ficou de fora de toda a análise financeira/jurídica até agora, identificado em revisão crítica do PO sobre `specifications/04-modelo-dados.md`.

**Responsável:** Jurídico + Produto.

---

## B006, Gateway de Pagamento e Pix no MVP

**Status:** Resolvido provisoriamente (2026-08-17, decisão de Produto do PO). Registrado como `adr/ADR-005-gateway-pagamento.md`. Não é bloqueador jurídico por si só (o parecer de B001/B005 sobre enquadramento de conta de pagamento continua aberto), mas deixa de bloquear a especificação da integração real do épico Financeiro.

**Impacta:** Modelo de Dados · APIs · Fluxo Financeiro · Integrações · Onboarding do profissional (subconta Asaas)

**Decisão:**
- Gateway único do MVP: **Asaas**. Pesquisa Mercado Pago × Stripe × Asaas no corpo do ADR-005 (reautorização, split nativo, taxas, presença no Brasil, Pix).
- **Pix aceito no MVP**, com ajuste de modelo só nesse método: captura imediata, `PaymentAuthorization` nasce `CAPTURADO`, dinheiro retido na conta Asaas da plataforma até `REPASSADO`. Cartão permanece autorizar → capturar → repassar (`adr/ADR-002-financeiro.md`).
- Resolve as três pendências residuais do ADR-002 (Pix, INV-046, INV-044).

**Responsável:** Produto (decidido). Fica aberto apenas MCC/janela de 25 dias na abertura da conta e o parecer jurídico de B001 se contradisser a premissa de custódia no Asaas.

---

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Criação a partir da revisão do PO sobre a estrutura de `foundation/`. |
| 2026-08-17 | Adiciona B005 (responsabilidade civil por dano ao imóvel), identificado em segunda revisão crítica do PO. |
| 2026-08-17 | Adiciona bloqueador de percentual de reserva financeira de garantia, identificado em terceira revisão crítica do PO (depois fundido em B001, ver linha seguinte). |
| 2026-08-17 | Quarta revisão do PO: funde o bloqueador de percentual de reserva em B001, mesmo parecer jurídico cobre responsabilidade da garantia e se a reserva financeira que a lastreia caracteriza retenção de fundo de terceiro (mesmo enquadramento que `ADR-002-financeiro.md` evitou). Não existe mais como item numerado separado. |
| 2026-08-17 | Quinta revisão do PO: B001 parcialmente resolvido (decisão provisória sem retenção, destrava desenvolvimento, parecer jurídico definitivo continua bloqueado). B002 resolvido provisoriamente (72h, `adr/ADR-004-prazo-aceite-automatico.md`). B003 sai de "Bloqueado" simples para "Em elaboração", rascunho de regras em `03-cancellation-rules.md`. |
| 2026-08-17 | B006 resolvido provisoriamente: Asaas no MVP, Pix aceito com captura imediata (`adr/ADR-005-gateway-pagamento.md`). Pendências residuais do ADR-002 deixam de bloquear o épico Financeiro. |
