# 03: Regras de Cancelamento (B003)

> Status: **Resolvido provisoriamente** (2026-08-17, sexta revisão do PO). Percentual de multa do Cenário B, mecânica de captura parcial sobre autorização e critério de resolução de `Em Contestação` registrados para destravar implementação. Multa pode ser revisada por Jurídico (direito do consumidor); mediação no MVP é manual por Admin/PO (sem time de moderação, mesmo raciocínio de `specifications/09-mecanismo-antidesintermediacao.md` §3).

## Os quatro cenários

| Cenário | Momento | Quem pode cancelar | Consequência | Reembolso | Estado seguinte |
|---|---|---|---|---|---|
| A | Antes de proposta aceita (Solicitação `Aberta`/`Recebendo Propostas`) | Cliente | Cancela livremente | N/A, nenhum pagamento foi autorizado ainda (autorização só ocorre quando a proposta é aceita, `adr/ADR-002-financeiro.md`) | Solicitação `Cancelada` |
| B | Após proposta aceita, antes de iniciar (Serviço `Agendado`) | Cliente | Multa decrescente por antecedência (tabela abaixo) | Parcial: captura só a multa, restante da autorização liberado (ver seção "Mecânica financeira, Cenário B") | Serviço `Cancelado` |
| C | Durante execução (Serviço `Em Andamento`) | Cliente/Profissional | Nunca cancela diretamente, abre disputa | Depende da resolução da disputa (ver seção "Resolução de `Em Contestação`") | Serviço `Em Contestação` |
| D | Após conclusão (Serviço `Aprovado`) | Ninguém | Não existe cancelamento, existe contestação/garantia | Conforme mediação | Ver nota "Cenário D" abaixo |

## Cenário B: percentual de multa

Multa calculada sobre `Proposta.valor` (centavos), com base na **antecedência** em relação à data/hora agendada (`Agenda.data` + `Agenda.hora` do serviço). Se não houver slot de agenda, usa `Solicitacao.data_desejada` às 00:00 UTC.

| Antecedência (horas até o agendamento) | Multa (% do valor da proposta) |
|---|---|
| ≥ 48 h | 10% |
| ≥ 24 h e < 48 h | 25% |
| < 24 h | 50% |

Parâmetros na tabela `Configuração` (`04-modelo-dados.md`), não constantes em código:

- `CANCELLATION_PENALTY_TIER1_HOURS` = 48, `CANCELLATION_PENALTY_TIER1_PERCENT` = 10
- `CANCELLATION_PENALTY_TIER2_HOURS` = 24, `CANCELLATION_PENALTY_TIER2_PERCENT` = 25
- `CANCELLATION_PENALTY_TIER3_PERCENT` = 50 (faixa < tier 2)

`valor_multa = floor(proposta.valor × percentual / 100)`. Se `valor_multa = 0` (edge case de proposta mínima), cancela sem captura.

**Validação jurídica pendente:** percentuais são chute inicial de produto para destravar desenvolvimento; Jurídico pode exigir ajuste (CDC, cláusula de adesão).

## Mecânica financeira, Cenário B

Neste ponto o pagamento está só `AUTORIZADO`, não `CAPTURADO`. `PaymentRefund` (INV-043) só existe sobre valor capturado; aqui a operação é **captura parcial da autorização** (valor da multa) com liberação do saldo restante.

### Cartão (autorizar/capturar, `ADR-002-financeiro.md`)

Fluxo preferencial quando o gateway (D1, ainda "Necessita Validação" em `05-arquitetura.md`) suporta **captura parcial**:

1. Cliente cancela serviço `Agendado` (`POST /services/{id}/cancel`).
2. Sistema calcula `valor_multa`.
3. Se `valor_multa = 0`: `PaymentAuthorization` `AUTORIZADO → CANCELADO`, evento `CANCELADO` (libera 100%).
4. Se `valor_multa > 0`:
   - Gateway captura parcialmente `valor_multa` na autorização vigente.
   - Registra `PaymentEvent` tipo `CAPTURADO` com `payload.motivo = CANCELAMENTO_MULTA`, `payload.valor = valor_multa`.
   - Gateway libera o saldo não capturado da autorização.
   - `PaymentAuthorization.status = CAPTURADO` (terminal, INV-042; valor efetivamente retido = multa, documentado no evento).
   - `PaymentSplit` calculado sobre o evento de captura parcial (comissão sobre a multa).
   - Repasse da parcela do profissional segue o fluxo normal pós-captura (job de repasse após janela, mesmo sem serviço `APROVADO`, pois houve captura).

**Dependência de D1:** se o gateway escolhido **não** suportar captura parcial, usar fallback documentado em `04-modelo-dados.md` (captura integral imediata + `PaymentEvent REEMBOLSADO` do excedente). Implementação concreta do adapter fica bloqueada até D1 fechar.

### Pix

Pix não tem autorizar/capturar (`ADR-002-financeiro.md`). No MVP o Pix nasce `PENDENTE` (INV-047, corrigido em 2026-08-20; a versão original deste documento assumia captura imediata, mas a cobrança Pix no Asaas não confirma na hora). Dois casos, dependendo de a autorização já ter sido confirmada pelo webhook ou não:

- **Ainda `PENDENTE`** (webhook não confirmou): não há dinheiro retido, não existe multa possível. `ApplyCancellationPenalty` cancela a cobrança pendente no gateway e encerra a autorização (`CANCELAMENTO_PIX_NAO_CONFIRMADO`), independente do percentual calculado pela tabela de antecedência.
- **Já `CAPTURADO`** (webhook confirmou antes do cancelamento): Cancelamento Cenário B vira **`PaymentRefund` parcial** ao cliente (`valor_proposta - valor_multa`), retendo a multa até o `REPASSADO` da parcela do profissional (INV-041).

Ver caminho Pix em `04-modelo-dados.md` e ciclo de vida completo (incluindo expiração e reconciliação com o gateway) em `foundation/02-state-machine.md` §4a-Pix.

## Resolução de `Em Contestação`

Vale para **Cenário C** (cancelamento durante execução) e **Cenário D-dentro-da-janela** (contestação de conclusão, FA004, serviço em `Aguardando Aprovação`). Não cobre garantia pós-`Aprovado` (mecanismo distinto, B001/`adr/ADR-003-garantia.md`).

### Quem decide (MVP)

**Admin manual** (`tipo = ADMIN` em `Usuario`, `PUT /disputes/{id}/resolve`). Não há time de moderação no MVP; o PO opera como Admin (mesmo raciocínio de `specifications/09-mecanismo-antidesintermediacao.md` §3: régua automática onde couber, decisão de mérito manual onde não couber).

Admin analisa evidências já existentes no fluxo: chat (`Mensagem`), fotos da solicitação/conclusão, descrição da disputa, histórico de `Auditoria`. Não há workflow de upload extra no MVP.

### Tipos de disputa (`PaymentDispute.tipo`)

| Tipo | Origem (estado anterior) | `resultado = APROVADO` | `resultado = CANCELADO` |
|---|---|---|---|
| `CONTESTACAO_CONCLUSAO` | `Aguardando Aprovação` (FA004, `POST /services/{id}/contest`) | Contestação improcedente → Serviço `Aprovado`, captura integral | Contestação procedente → Serviço `Cancelado`, libera autorização (sem captura) |
| `CANCELAMENTO_EXECUCAO` | `Em Andamento` (`POST /services/{id}/cancel`, Cenário C) | Pedido de cancelamento negado → Serviço **retorna a `Em Andamento`** (execução continua) | Pedido aceito → Serviço `Cancelado`, libera autorização integral (sem multa; execução já havia iniciado) |

`CANCELAMENTO_EXECUCAO` com resultado `APROVADO` é a única transição de `Em Contestação` que **não** termina em `Aprovado`/`Cancelado` terminal; retoma `Em Andamento` (exceção documentada em `02-state-machine.md` §3).

### Prazo e timeout

Parâmetro `DISPUTE_MEDIATION_DAYS` na tabela `Configuração` = **7 dias corridos** a partir de `PaymentDispute.aberta_em`.

Job diário: disputas `ABERTA` com prazo esgotado resolvem **automaticamente** (registra `Auditoria` com `justificativa = TIMEOUT_AUTOMATICO`):

| Tipo | Timeout (sem decisão Admin) | Efeito no Serviço | Efeito financeiro |
|---|---|---|---|
| `CONTESTACAO_CONCLUSAO` | → `resultado = APROVADO` | `Aprovado` | Captura integral (mesma lógica do aceite automático de B002, disputa pausava o timer de `AUTO_APPROVAL_HOURS`) |
| `CANCELAMENTO_EXECUCAO` | → `resultado = CANCELADO` | `Cancelado` | Libera autorização integral (plataforma não conseguiu mediar a tempo; favorece encerrar serviço disputado) |

Timeout **não** suspende direito do Admin de decidir antes do job; após resolução (manual ou automática), `PaymentDispute.status = RESOLVIDA`.

### Critério de mérito (orientação Admin, não algoritmo)

- **`CONTESTACAO_CONCLUSAO`:** `APROVADO` se evidências não sustentam falha grave no serviço entregue; `CANCELADO` se evidências indicam serviço não conforme ao escopo acordado (`Solicitacao.escopo`) ou não executado.
- **`CANCELAMENTO_EXECUCAO`:** `CANCELADO` se motivo do cancelamento é razoável (impossibilidade de continuar, descumprimento grave da outra parte); `APROVADO` (retoma execução) se pedido é oportunista ou contradiz evidências de execução normal.

Decisão exige `justificativa` no request (auditoria, INV-070).

## Mapeamento para o domínio já modelado

**Cenário A** já está coberto por `02-state-machine.md` §1 (Solicitação: `Aberta|Recebendo Propostas --(cliente: cancela)--> Cancelada`, FA002). Trivial financeiramente: pagamento só é autorizado quando a proposta é aceita (`Contratada`, que dispara criação do `Serviço`), então cancelar antes disso não tem nada para estornar.

**Cenário B** coberto por este documento (multa + captura parcial) e `04-modelo-dados.md` (entidades de pagamento). Cartão: captura parcial da autorização (ou fallback captura+reembolso se o Asaas não suportar parcial, `adr/ADR-005-gateway-pagamento.md`). Pix: se ainda `PENDENTE`, só cancela a cobrança (sem multa); se já `CAPTURADO`, multa é `PaymentRefund` parcial (ver seção "Mecânica financeira, Cenário B" acima, corrigida em 2026-08-20).

**Cenário C** coberto pela seção "Resolução de `Em Contestação`", tipo `CANCELAMENTO_EXECUCAO`.

**Cenário D**, "após conclusão", mapeia para dois mecanismos diferentes dependendo do momento exato:
- **Dentro da janela de aceite automático** (`Aguardando Aprovação`): contestação de conclusão, tipo `CONTESTACAO_CONCLUSAO`, mesma seção de resolução acima.
- **Depois de `Aprovado`**: acionamento de **Garantia** (`02-state-machine.md` §5), sem relação com cancelamento deste documento.

## O que fica pendente (fora do escopo desta decisão)

- Validação jurídica definitiva dos percentuais de multa (Cenário B).
- Implementação concreta do adapter de captura parcial vs. fallback no Asaas (`adr/ADR-005-gateway-pagamento.md`: captura parcial ainda não assumida; D1/adapter).
- Impacto de cancelamento/contestação recorrente na reputação: limiares e recálculo já estão em `foundation/05-trust-level.md` (RN008, gatilho `DisputaResolvida` / P8); não bloqueia esta decisão.

## Responsável

Produto (decisões provisórias registradas). Jurídico valida multa antes de produção. Admin/PO opera mediação no MVP.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 | Criado a partir do rascunho do PO sobre B003 (`04-decisions-pending.md`). Mapeia os 4 cenários para os estados já existentes em `02-state-machine.md`, registra Cenário A como já coberto, Cenário B/C como decisão parcial de fluxo (sem valores fechados), Cenário D como dois mecanismos distintos (contestação de conclusão vs. garantia), não um só. |
| 2026-08-17 | Cenário B: distingue cartão (ainda `AUTORIZADO`) de Pix (já `CAPTURADO`, `adr/ADR-005-gateway-pagamento.md`). |
| 2026-08-17 | B003 resolvido provisoriamente: multa decrescente Cenário B (10/25/50%), mecânica de captura parcial, resolução de `Em Contestação` (Admin manual, 7 dias, timeout por tipo), parâmetros em `Configuração`. |
| 2026-08-20 | Corrige a seção Pix da mecânica financeira: Pix nasce `PENDENTE`, não `CAPTURADO` (INV-047, `02-state-machine.md` §4a-Pix). Cancelamento de Pix ainda `PENDENTE` não tem multa (só cancela a cobrança); o caminho de `PaymentRefund` parcial descrito originalmente só se aplica se o webhook já tiver confirmado a captura antes do cancelamento. |
