# ADR-005: Gateway de Pagamento e Pix no MVP

**Status:** Decisão provisória de produto (mesmo padrão de `ADR-003-garantia.md` / `ADR-004-prazo-aceite-automatico.md`). Pode ser revista se o parecer jurídico de B001, a elegibilidade de MCC da conta Asaas ou a contratação comercial mudarem as premissas abaixo.

**Data:** 2026-08-17 (PO)

## Contexto

`ADR-002-financeiro.md` fechou a modalidade **autorizar → capturar → repassar** (não escrow bancário), mas deixou três dependências sem provedor: (1) reautorização de cartão quando a janela de autorização expira antes do serviço (INV-046), (2) split nativo no momento da captura (INV-044), (3) Pix, que é captura imediata e o método dominante no Brasil. `specifications/05-arquitetura.md` listava Mercado Pago, Stripe e Asaas como candidatos, todos em "Necessita Validação". Sem essa escolha o bounded context Payment permanece abstrato e o épico Financeiro não tem integração real para especificar.

Pesquisa registrada abaixo, contra documentação pública dos três provedores e referência de mercado de meados de 2026. Taxas mudam com volume e contrato: tratar os números como ordem de grandeza, confirmar na contratação.

## Pesquisa de mercado

### Comparativo (o que o modelo exige)

| Critério | Mercado Pago | Stripe | Asaas |
|---|---|---|---|
| Presença no Brasil | Nativa, subadquirente, marca conhecida do consumidor | Operação BR ativa; Pix para empresas BR sob convite | Nativa. Instituição de pagamento autorizada pelo BCB |
| Pix | Sim, first-class, split no settlement da cobrança | Sim, via PaymentIntents; disponibilidade Connect/Pix para PJ BR sob convite | Sim, first-class (cobrança `billingType=PIX`) |
| Autorizar/capturar (cartão) | Sim (`capture_mode: manual` na Orders API). Janela **5 dias**, depois cancela. Captura parcial existe no Checkout Bricks; Orders API documenta captura total | Sim (`capture_method: manual`). Janela **7 dias** (padrão). Extended authorization até ~30 dias se a bandeira aceitar | Sim (`authorizeOnly: true`, status `AUTHORIZED`). Prazo padrão **3 dias**, configurável até **25 dias** em contas elegíveis (MCC / regras de bandeira) |
| Reautorização de autorização expirada | **Não nativa.** Cartão salvo (`/v1/customers/{id}/cards`) em geral exige **CVV de novo** para gerar token. INV-046 off-session (serviço agendado, cliente ausente) fica frágil | **Melhor DX.** SetupIntent + PaymentIntent off-session (MIT). Autorização expirada = novo PaymentIntent no cartão salvo, sem o cliente presente, se o cartão foi tokenizado com `setup_future_usage` / off-session | **Suficiente para o modelo.** `creditCardToken` reutiliza o cartão. Autorização expirada = nova cobrança `authorizeOnly` (é exatamente INV-046: nova `PaymentAuthorization`, evento `REAUTORIZADO`). Sem API de "estender" a autorização original |
| Split nativo | Sim (marketplace): `application_fee` / `marketplace_fee`. Split no settlement. Recebedor precisa de conta MP vinculada via OAuth | Sim (Connect): destination charges, separate charges and transfers, `application_fee_amount` na captura. Separate charges and transfers permite reter e transferir depois (útil para Pix) | Sim (API nativa, campo `splits` / `walletId`). Split liquida **no settlement da cobrança**. Transferência interna posterior (`POST /v3/transfers`, sem custo entre conta raiz e subconta) cobre o caso em que o split **não** pode ir na cobrança |
| Taxas (ordem de grandeza, meados de 2026) | Cartão ~3,03% a 4,98% conforme prazo de recebimento (30d / 14d / na hora); Pix percentual (~0,99% a 1,99%, varia por conta). Sem tarifa fixa típica no cartão | Cartão com percentual + tarifa fixa (referência pública na casa de ~3,99% + R$ 0,39). Pix com taxa própria, confirmar na conta BR | Sem mensalidade. Cartão padrão **2,99% + R$ 0,49** (promo 3 meses menor). Pix cobrança: **R$ 1,99** por fatura recebida (R$ 0,99 nos 3 primeiros meses). Transferência interna entre contas Asaas: sem custo |
| Encaixa no ADR-002 (cartão) | Autorizar/capturar sim, mas janela de 5 dias + CVV na reautorização tensiona INV-046 em serviço agendado (2+ semanas) | Autorizar/capturar e reautorização off-session excelentes. Pix e Connect no BR são o ponto frágil do MVP | Autorizar/capturar sim. Janela configurável reduz (não elimina) INV-046. Split nativo no settlement da captura de cartão. Pix exige o ajuste de modelo abaixo |
| Risco regulatório de Pix retido | Saldo fica no arranjo Mercado Pago (subadquirente), não na conta bancária da plataforma | Separate charges and transfers segura saldo na Stripe (IP estrangeira com operação BR) | Saldo fica na conta Asaas da plataforma / subcontas. Asaas é IP autorizada pelo BCB. A plataforma não custodia em conta própria |

Nenhum dos três tem um endpoint chamado "reautorizar". O padrão da indústria é: tokenizar o cartão e criar uma **nova** pré-autorização quando a anterior expira. Isso já está no modelo (`Serviço` 1:N `PaymentAuthorization`, evento `REAUTORIZADO`, INV-046). A pergunta real é se a nova autorização pode ser criada **sem o cliente na tela**.

Pagar.me (Grupo Stone) aparece em `ADR-003-garantia.md` como referência de split no momento da captura. Não entrou no critério mínimo desta issue (Mercado Pago × Stripe × Asaas). Continua candidato pós-MVP se o split precisar de regras mais complexas do que 2 recebedores (plataforma + profissional).

## Decisão

### Gateway do MVP: Asaas

Um único gateway no MVP (dívida já aceita em `specifications/08-planejamento.md`: "Sem múltiplos gateways de pagamento").

Motivos, nesta ordem:

1. **INV-044 (split nativo).** Split na API, liquidado no settlement. Em cartão, settlement = captura, que é o momento em que o domínio calcula `PaymentSplit`.
2. **INV-046 (reautorização).** Token de cartão + nova `authorizeOnly` cobre o job de reautorização sem exigir CVV na hora. Janela de 3 a 25 dias reduz quantas vezes o job dispara em serviço agendado; mesmo no pior caso (3 dias) o caminho existe.
3. **Pix first-class** sem convite, com conta de pagamento regulada no Brasil, necessário para a decisão de Pix abaixo.
4. **Custo e operação de MVP.** Sem mensalidade, taxas públicas previsíveis, docs e suporte em português, sandbox. Stripe Connect + Pix-sob-convite é complexidade prematura para lançamento em uma cidade. Mercado Pago tem marca mais forte no consumidor, mas a reautorização off-session (CVV) e a janela rígida de 5 dias quebram o caso de serviço agendado.

Abstrair o Asaas atrás de uma porta de `PaymentGateway` no código (já sugerido em `specifications/07-engenharia.md`) para não espalhar SDK no domínio. Trocar de provedor depois é revisão deste ADR, não um segundo gateway no MVP.

### Pix no MVP: aceitar, com ajuste de modelo só nesse método

Não aceitar Pix na v1 seria uma decisão de produto disfarçada de efeito colateral do ADR-002. Pix é o método dominante no Brasil; um marketplace presencial que só aceita cartão no lançamento perde conversão no momento em que o cliente deveria "pagar protegido".

O ajuste (somente `metodo = PIX`):

```
Cliente aceita proposta
      ↓
Cobra Pix (captura imediata no Asaas)
      ↓
PaymentAuthorization já nasce CAPTURADO
PaymentSplit calculado agora (INV-044, alíquota vigente)
Valor permanece na conta Asaas da plataforma (IP autorizada)
      ↓
Serviço executado → cliente aprova ou AUTO_APPROVAL_HOURS
      ↓
Evento REPASSADO: transferência interna Asaas (walletId do profissional)
```

O que **não** muda: cartão continua autorizar → capturar → repassar (`ADR-002-financeiro.md`). INV-041, lida com precisão, bloqueia o **repasse** (liberação ao profissional) antes da aprovação; o parêntese antigo "capturado + repassado" descrevia o cartão, não proíbe captura imediata de Pix. O dinheiro do Pix **não** entra em conta bancária da plataforma: fica custodiado pelo Asaas. Isso não reabre escrow bancário rejeitado pelo ADR-002; a plataforma não é instituição de pagamento.

Regra operacional de Pix no Asaas: **não** enviar `splits` na cobrança Pix. Split na cobrança liquidaria na hora e pagaria o profissional antes do serviço. O `PaymentSplit` no domínio continua sendo calculado no `CAPTURADO`; o movimento de dinheiro correspondente é a transferência no `REPASSADO`. Cartão usa split nativo na captura (settlement = captura).

INV-046 **não dispara** para Pix: não há autorização a expirar. Cancelamento de serviço com Pix já capturado é `PaymentRefund` (INV-043), não cancelamento de autorização. A mecânica de multa/captura parcial do Cenário B (`foundation/03-cancellation-rules.md`, B003) continua aberta; em Pix ela é reembolso parcial sobre valor capturado, não captura parcial de autorização.

## Mapeamento às pendências do ADR-002

| Pendência do ADR-002 | Resolução neste ADR |
|---|---|
| Pix incompatível com autorizar/capturar | Aceito no MVP com o fluxo acima. Cartão permanece autorizar/capturar |
| Reautorização (INV-046) depende do gateway | Asaas: nova cobrança `authorizeOnly` com `creditCardToken`. Janela 3 a 25 dias. Sem CVV na reautorização |
| Split nativo na captura (INV-044) | Cartão: `splits` na cobrança, liquida na captura. Pix: split de domínio no `CAPTURADO`, dinheiro no `REPASSADO` via transferência interna |

## Consequências

- `PaymentAuthorization.metodo` no MVP: `CARTAO | PIX`. Pix nasce `CAPTURADO`; cartão nasce `AUTORIZADO`.
- Profissional precisa de subconta Asaas (`walletId`) no onboarding (RF002/verificação), senão não há destino de `REPASSADO`.
- Confirmar na abertura da conta Asaas se o MCC do marketplace é elegível à janela de 25 dias. Se não for, INV-046 opera no padrão de 3 dias; o modelo já aguenta.
- `specifications/05-arquitetura.md` deixa de listar o gateway como "Necessita Validação".
- B003 (captura parcial de autorização no Cenário B, cartão) agora tem provedor concreto: o endpoint de captura Asaas captura a pré-autorização vigente; captura parcial continua **não** assumida, permanece pendência de B003.

## O que fica fora

- Segundo gateway no MVP.
- Boleto, carteira Mercado Pago, Apple/Google Pay.
- Parcelamento (já pendente em `04-modelo-dados.md`).
- Pagar.me / Stripe Connect como implementação v1.
- Parecer jurídico sobre a plataforma ser ou não conta de pagamento (B001/B005). Premissa de trabalho: custódia no Asaas, não em conta própria. Se o parecer contradisser isso, este ADR reabre junto com o ADR-002.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 | Decisão provisória de produto: Asaas no MVP; Pix aceito com captura imediata + retenção no gateway até `REPASSADO`. Pesquisa Mercado Pago × Stripe × Asaas registrada. Resolve as três pendências residuais do `ADR-002-financeiro.md` (B006). |
