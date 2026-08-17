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

Relacionado a `INV-031` e `INV-041` (`00-domain-invariants.md`): o pagamento do serviço executado só é **repassado** após aprovação do cliente **ou** o esgotamento desta janela sem contestação. Captura de Pix é imediata no aceite (`adr/ADR-005-gateway-pagamento.md`); captura de cartão no caminho feliz continua após aprovação. Exceção: multa do Cenário B (`03-cancellation-rules.md`) pode ser capturada/repassada sem `APROVADO`.

**Responsável:** Produto (decidido). Fica aberto apenas o ajuste fino do valor após dado real de uso, não bloqueia desenvolvimento.

---

## B003, Cancelamento

**Status:** Resolvido provisoriamente (2026-08-17, sexta revisão do PO). Regras registradas em `foundation/03-cancellation-rules.md`. Destrava implementação de cancelamento (Cenário B), disputas (`PaymentDispute`) e `PUT /disputes/{id}/resolve`. Percentuais de multa e timeout de mediação podem ser ajustados por configuração; validação jurídica da multa e adapter concreto de captura parcial continuam pendentes.

**Decisão provisória do PO (2026-08-17):**
- **Cenário B (multa):** tabela decrescente por antecedência ao agendamento: ≥48h → 10%, ≥24h → 25%, <24h → 50% do valor da proposta. Parâmetros `CANCELLATION_PENALTY_*` em `Configuração`.
- **Cenário B (financeiro):** captura parcial da autorização `AUTORIZADO` (valor = multa), libera saldo restante; fallback captura+reembolso se gateway não suportar parcial (depende de D1). Pix: reembolso parcial pós-captura imediata.
- **Resolução de `Em Contestação`:** Admin manual no MVP (`Usuario.tipo = ADMIN`); prazo `DISPUTE_MEDIATION_DAYS` = 7 dias; timeout automático: `CONTESTACAO_CONCLUSAO` → `Aprovado` (captura), `CANCELAMENTO_EXECUCAO` → `Cancelado` (libera autorização). Tipos de disputa e transições em `02-state-machine.md` §3.

**O que continua pendente:**
- Parecer jurídico definitivo sobre percentuais de multa (CDC).
- Implementação do adapter Asaas para captura parcial vs. fallback (captura parcial ainda não assumida, `adr/ADR-005-gateway-pagamento.md`).
- Impacto de cancelamento/contestação recorrente na reputação: limiares já em `foundation/05-trust-level.md` (RN008); não bloqueia B003.

**Responsável:** Produto (decidido provisoriamente). Jurídico valida multa. Admin/PO opera mediação no MVP.

---

## B004, Histórico Manual do Imóvel

**Status:** Bloqueado (decisão provisória do PO já registrada, falta validação)

**Alternativas avaliadas:**
- **A, Somente registros gerados pela plataforma.** Prós: dados confiáveis. Contras: histórico incompleto.
- **B, Permitir registros manuais.** Prós: aumenta o valor do prontuário. Contras: reduz confiabilidade.

**Decisão provisória do PO (2026-08-16): modelo híbrido.** Todo registro tem uma `origem` (`PLATAFORMA | MANUAL | IMPORTADO`) com selo de confiabilidade próprio, um comprador futuro do imóvel distingue manutenção comprovada por serviço contratado na plataforma de anotação manual do proprietário. Refletido em `00-domain-invariants.md` (INV-062).

**Responsável:** Produto (validar antes de fechar `specifications/04-modelo-dados.md`).

## B005, Responsabilidade Civil por Dano ao Imóvel

**Status:** Parcialmente resolvido. Decisão provisória de produto registrada em 2026-08-17 (sexta revisão do PO) para **destravar desenvolvimento** (Termos de Uso, RF002, verificação documental), mas o parecer jurídico definitivo continua bloqueado, esta decisão pode mudar.

**Impacta:** Termos de Uso · Fluxo Financeiro · Verificação de Profissional (RF002) · State Machine (disputa durante execução, B003)

Nenhum documento até 2026-08-17 tratava de quem responde quando um profissional causa dano ao imóvel durante a execução (ex.: vazamento em serviço hidráulico, dano elétrico). É o maior risco reputacional de um marketplace presencial e ficou de fora de toda a análise financeira/jurídica até agora, identificado em revisão crítica do PO sobre `specifications/04-modelo-dados.md`.

### Pesquisa de mercado (referência, não substitui parecer jurídico)

| Plataforma | Modelo observado | Fonte (2026-08) |
|---|---|---|
| **TaskRabbit** (EUA) | Não oferece seguro nem assume responsabilidade pelos taskers. Taskers são pessoalmente responsáveis; a plataforma recomenda que contratem seguro próprio. Oferece "Happiness Pledge"/Taskprotect: compensação **discricionária**, caso a caso, até ~US$ 10 mil, **não é apólice**; cliente deve esgotar seguro residencial/pessoal antes. | [Taskrabbit Support](https://support.taskrabbit.com/hc/en-us/articles/46260501460891), [Happiness Pledge](https://support.taskrabbit.com/hc/en-us/articles/46260466252059) |
| **GetNinjas** (BR) | Classificado online; declara-se isenta de responsabilidade pela execução, qualidade ou danos causados pelo profissional. Negociação e pagamento ocorrem fora da plataforma (modelo legado). Oferece mediação informal, sem garantia financeira. Precedentes judiciais pontuais reconhecem responsabilidade solidária em casos de falha de segurança na curadoria de prestadores. | [Termos de Uso](https://www.getninjas.com.br/termos-de-uso), seção 16 |
| **Angi / HomeAdvisor** (EUA) | Marketplace com pagamento na plataforma. **Exige** que profissionais tenham seguro de responsabilidade civil geral (CGL, mín. US$ 500 mil a US$ 2 mi) e apresentem certificado antes de iniciar trabalho; Angi como segurado adicional. "Happiness Guarantee" cobre danos **secundariamente** (após seguro do profissional/cliente), com tetos por tipo de serviço (US$ 2,5 mil a US$ 50 mil). | [Angi Happiness Guarantee](https://www.angi.com/landing/happiness-guarantee), contrato de prestador (CGL obrigatório) |

**Leitura para o Resolve+:** o modelo GetNinjas (zero responsabilidade, pagamento off-platform) não se aplica: o Resolve+ participa da transação inteira (pagamento protegido, garantia, prontuário). O modelo TaskRabbit (pledge discricionário da plataforma) expõe a plataforma a risco financeiro, inconsistente com B001/ADR-003. O modelo Angi (seguro obrigatório do profissional + cobertura secundária da plataforma) é o mais próximo do perfil de risco desejado, adaptado abaixo **sem** a cobertura financeira discricionária da plataforma (mesma linha de B001: plataforma media, não paga).

### Decisão provisória do PO (2026-08-17)

- **Responsabilidade primária:** do **profissional**, por analogia ao Modelo B de B001 (`adr/ADR-003-garantia.md`): quem executa presencialmente no imóvel responde pelos danos causados durante a execução.
- **Papel da plataforma:** **mediadora**, não parte financeiramente responsável. A plataforma **não** mantém fundo indenizatório, **não** oferece "pledge" discricionário (TaskRabbit) nem cobertura secundária própria (Angi Happiness Guarantee) no MVP.
- **Seguro do profissional (RF002):** **obrigatório** comprovante de apólice de responsabilidade civil (RC) vigente no cadastro/verificação do profissional. Refletido em `DocumentoProfissional` (`specifications/04-modelo-dados.md`, tipo `SEGURO_RC`). Renovação: profissional com apólice vencida não recebe novas solicitações até revalidar (status de conta permanece `ATIVA`, mas RF002 bloqueia oportunidades, detalhe de implementação futura).
- **Sinistro durante execução:** usa o fluxo existente de disputa (`Serviço` `Em Andamento` → `Em Contestação`, Cenário C de `foundation/03-cancellation-rules.md`, B003). **Não criar entidade `Sinistro` no MVP**; evidências (fotos, descrição) ficam no registro de disputa/mediação. Resolução de mérito segue a tabela de `CANCELAMENTO_EXECUCAO` em `03-cancellation-rules.md` (Admin + timeout 7d).
- **Compromisso da plataforma em caso de sinistro:** (1) mediação entre cliente e profissional via fluxo de disputa; (2) **facilitar acionamento** da apólice RC do profissional (disponibilizar dados da apólice ao cliente mediante solicitação formal, registrar tentativas de contato); (3) **não** indenizar diretamente nem reter/repassar valores da plataforma para cobrir dano (consistente com B001, sem retenção adicional sobre repasse).

**O que continua bloqueado (parecer jurídico definitivo):** se a decisão provisória (profissional responde, plataforma só media, seguro RC exigido no cadastro) é juridicamente suficiente para um marketplace com pagamento on-platform no Brasil (CDC, responsabilidade solidária da plataforma); valor mínimo de cobertura da apólice RC; se a plataforma precisa ser segurada adicional na apólice (modelo Angi); se sinistro exige entidade/fluxo próprio além de `Em Contestação`. Se o parecer mudar a decisão provisória, `specifications/04-modelo-dados.md` (`DocumentoProfissional`), RF002, Termos de Uso e `foundation/03-cancellation-rules.md` precisam ser revisados juntos.

> **Disclaimer:** esta decisão provisória **não substitui parecer jurídico definitivo**, mesmo texto aplicável a B001 (`adr/ADR-003-garantia.md`). Existe para destravar modelagem e redação de Termos de Uso; pode mudar após validação jurídica.

**Responsável:** Jurídico + Produto (parecer definitivo). Decisão provisória já registrada pelo PO.

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
| 2026-08-17 | Sexta revisão do PO: B005 parcialmente resolvido (decisão provisória: profissional responde, plataforma media, seguro RC obrigatório no cadastro via RF002/`DocumentoProfissional`, sinistro usa fluxo `Em Contestação`, parecer jurídico definitivo continua bloqueado). Pesquisa de mercado (TaskRabbit, GetNinjas, Angi) registrada. |
| 2026-08-17 | Sexta revisão do PO: B003 resolvido provisoriamente (multa Cenário B, captura parcial, mediação Admin + timeout 7d, `03-cancellation-rules.md`). |
