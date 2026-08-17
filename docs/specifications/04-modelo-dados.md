# 04 — Modelo de Dados do MVP

> Revisado em 2026-08-17 para eliminar contradições com `foundation/00-domain-invariants.md` e `foundation/02-state-machine.md`, apontadas em análise crítica do PO. A versão anterior mantinha uma entidade `Contratacao` (proibida por INV-020), um `Pagamento` 1:1 simples (proibido por INV-040 a INV-045) e um `HistoricoImovel` de log raso (proibido por INV-060 a INV-062). Onde este documento e `00-domain-invariants.md` divergirem no futuro, o invariante vence — ver regra no topo de `00-domain-invariants.md`.

## Visão Geral

O domínio é centrado em uma **Solicitação de Serviço**, que recebe **Propostas**. O aceite de uma proposta é um **evento** (`ProposalAccepted`), não uma entidade — ele dispara a criação de um **Serviço**. O Serviço gera eventos de **Pagamento** (bounded context próprio), uma **Garantia** e ao menos uma **Intervention** no prontuário do **Property** (imóvel).

## Diagrama Conceitual

```
Usuário
 ├── Cliente
 └── Profissional
        │
        ▼
Categoria
        │
        ▼
Solicitação ──(property_id)──> Property ── Area ── Asset ── Intervention
        │
        ├── FotoSolicitacao
        └── Proposta ──(evento: ProposalAccepted, INV-020)──┐
                                                              ▼
                                                          Serviço
                                                           ├── Agenda
                                                           ├── Mensagem
                                                           ├── Avaliação
                                                           ├── Garantia
                                                           └── PaymentAuthorization
                                                                ├── PaymentEvent (append-only)
                                                                ├── PaymentSplit
                                                                ├── PaymentRefund
                                                                └── PaymentDispute
```

Não existe tabela `Contratacao`. O relacionamento Proposta → Serviço é direto (`servico.proposta_id`); o evento `ProposalAccepted` fica registrado em `Auditoria`, não em entidade própria (INV-020).

## Convenções

- UUID como chave primária em todas as entidades.
- Todo valor monetário é `INTEGER` em centavos (alinhado a `06-apis.md`) — nunca `DECIMAL`/`FLOAT`.
- `criado_em`/`atualizado_em` em todas as tabelas.
- Enums deste documento são espelho direto dos estados definidos em `02-state-machine.md` — qualquer enum aqui que não tenha transição correspondente lá é bug de documentação, não uma opção válida.

## Entidades

### Usuario

| Campo | Tipo | Obrigatório | Índice |
|---|---|---|---|
| id | UUID | Sim | PK |
| tipo | ENUM(`TipoUsuario`) | Sim | Sim |
| nome | VARCHAR(150) | Sim | |
| email | VARCHAR(150) | Sim | Unique |
| telefone | VARCHAR(20) | Sim | |
| senha_hash | VARCHAR | Sim | |
| foto | VARCHAR | Não | |
| status | ENUM(`StatusConta`) | Sim | Sim |
| criado_em | TIMESTAMP | Sim | |

**Exclusão (LGPD, ainda sem validação jurídica final — ver `foundation/04-decisions-pending.md`):** nunca hard-delete. `status = EXCLUIDA` (INV-003/state machine §6) + anonimização dos campos identificáveis (`nome`, `email`, `telefone`, `foto` substituídos por placeholder). Preserva integridade referencial com Auditoria, Pagamento e Avaliação, que são append-only e não podem perder FK.

**Relacionamentos**: 1:N Solicitações, 1:N Propostas, 1:N Serviços, 1:N Avaliações, 1:N Endereço

### Endereco

**Campos**: id, usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, latitude, longitude

**Relacionamento**: Usuário 1:N Endereço

### Categoria

**Campos**: id, nome, descricao, ativo

### Property (Imóvel)

> Renomeado de `Imovel` para alinhar com `06-apis.md` (`/properties`, `property_id`) e com INV-061/062. Antes ligado a `cliente_id` como FK fixa — isso contradizia o glossário ("vinculado ao endereço/unidade, não à pessoa") e quebrava o prontuário na venda do imóvel. Agora o vínculo com o dono é mutável e separado da identidade do registro.

**Campos**: id, endereco_id, apelido

**Relacionamentos**: 1:1 Endereço · 1:N Area · 1:N PropertyOwnership

### PropertyOwnership

> Histórico de posse — permite trocar o dono (venda do imóvel) sem apagar nem reatribuir o histórico de `Intervention`, que referencia `Asset`/`Area`/`Property`, nunca o cliente diretamente.

**Campos**: id, property_id, cliente_id, desde, ate (NULL = dono atual)

**Regra de integridade**: no máximo um registro com `ate IS NULL` por `property_id`.

### Area

**Campos**: id, property_id, nome (ex.: "Cozinha"; usar `"Não especificado"` como fallback — INV-061)

### Asset

**Campos**: id, area_id, nome/tipo (ex.: "Torneira"; mesmo fallback de Area — INV-061)

### Intervention

> Substitui `HistoricoImovel`. Implementa INV-060/061/062: nunca solta (sempre referencia Asset dentro de Area dentro de Property) e sempre carrega origem.

**Campos**: id, asset_id, servico_id (NULL se `origem != PLATAFORMA`), data, categoria, resumo, origem (ENUM `PLATAFORMA | MANUAL | IMPORTADO`), confiabilidade (derivada da origem, não editável manualmente)

**Regra de criação**: todo `Serviço` que atinge `Aprovado`/`Finalizado` (INV-060) gera automaticamente Area/Asset "Não especificado" quando a granularidade não foi capturada no fluxo, e uma `Intervention` com `origem = PLATAFORMA`.

### Solicitacao

**Campos**: id, cliente_id, categoria_id, property_id, descricao, status, data_desejada, criado_em

> Campo renomeado de `endereco_id` para `property_id` — a versão anterior divergia de `06-apis.md`, que já usa `property_id` em `POST /requests`. Toda solicitação nasce vinculada a um imóvel (que carrega o endereço), não a um endereço solto, porque o prontuário (`Intervention`) precisa de um `Property` para existir.

**Relacionamentos**: Cliente, Categoria, Property, Fotos, Propostas

### FotoSolicitacao

**Campos**: id, solicitacao_id, url, ordem

### Proposta

**Campos**: id, solicitacao_id, profissional_id, valor (INTEGER, centavos), prazo_dias, garantia_dias, observacoes, status

**Índice obrigatório**: `UNIQUE (solicitacao_id) WHERE status = 'ACEITA'` — é o mecanismo físico que garante INV-010 (no máximo uma proposta aceita por solicitação); sem ele a invariante depende só de disciplina de aplicação.

### Servico

**Campos**: id, proposta_id, inicio, fim, status

> `contratacao_id` removido — não existe `Contratacao` (INV-020). O Serviço referencia a Proposta aceita diretamente; o evento `ProposalAccepted` que autorizou sua criação fica em `Auditoria`.

### Agenda

**Campos**: id, servico_id, data, hora, observacoes

### Mensagem

**Campos**: id, servico_id, remetente_id, texto, anexo, enviado_em

> Ver `09-mecanismo-antidesintermediacao.md` para o mascaramento de contato exigido neste fluxo (OBJ-NEG-03).

### Avaliação

> A versão anterior era unilateral (só cliente avalia profissional), o que é fraco para retenção do lado profissional num marketplace de dois lados.

**Campos**: id, servico_id, autor_id, alvo_id, direcao (ENUM `CLIENTE_AVALIA_PROFISSIONAL | PROFISSIONAL_AVALIA_CLIENTE`), nota, comentario

**Validação**: nota entre 1 e 5. No máximo uma avaliação por `(servico_id, direcao)`.

### Garantia

**Campos**: id, servico_id, inicio, fim, status (`StatusGarantia`)

## Bounded Context: Payment (INV-040 a INV-045)

> Substitui integralmente a antiga tabela `Pagamento` (`status: PENDENTE/RETIDO/LIBERADO/PAGO`), que usava vocabulário de escrow — rejeitado por `ADR-002-financeiro.md`. Modelo abaixo é o de eventos imutáveis já descrito em `00-domain-invariants.md` e nas transições de `02-state-machine.md` §4.

### PaymentAuthorization

**Campos**: id, servico_id, valor (INTEGER, centavos), metodo, status (`StatusPaymentAuthorization`: `AUTORIZADO | CAPTURADO | CANCELADO | EXPIRADO`), criado_em, expira_em

**Regra**: toda autorização termina em `CAPTURADO`, `CANCELADO` ou `EXPIRADO` — nunca fica em `AUTORIZADO` indefinidamente (INV-042). `expira_em` é o campo que sustenta essa regra via job.

### PaymentEvent

> Log append-only. Fonte de verdade do histórico financeiro — `PaymentAuthorization.status` é uma projeção/cache do último evento, nunca editada diretamente (INV-040).

**Campos**: id, payment_authorization_id, tipo (`AUTORIZADO | CAPTURADO | REPASSADO | CANCELADO | EXPIRADO | REEMBOLSADO`), payload (JSON), criado_em

**Regra de integridade**: sem `UPDATE`/`DELETE` — só `INSERT`.

### PaymentSplit

**Campos**: id, payment_event_id (o evento de captura), valor_profissional, valor_plataforma, aliquota_vigente

**Regra**: calculado no momento da captura com a alíquota vigente naquele instante; alterar a comissão depois não recalcula splits antigos (INV-044).

### PaymentRefund

**Campos**: id, payment_event_id (deve referenciar um evento `CAPTURADO`), valor, motivo, criado_em

**Regra**: só existe sobre valor já capturado. Sobre valor apenas autorizado, o evento correto é `CANCELADO`, não um `PaymentRefund` (INV-043).

### PaymentDispute

**Campos**: id, servico_id, status (`ABERTA | RESOLVIDA`), aberta_em, resolvida_em

**Regra**: enquanto `ABERTA`, bloqueia geração de evento `REPASSADO`, mas não bloqueia novos `PaymentEvent` de outro tipo (INV-045). Resolução de mérito depende de B003 (mediação) — ver `foundation/04-decisions-pending.md`.

## Auditoria

**Campos**: id, usuario_id, acao, entidade, id_entidade, data, ip, justificativa (obrigatório quando `acao` é liberação/captura administrativa fora do fluxo normal — INV-041)

## Tabelas Auxiliares

### Configuração
Parâmetros globais: comissão (%), prazo de garantia padrão, tempo limite para aceite automático (B002), raio máximo de atendimento.

### Notificação
**Campos**: usuario_id, titulo, mensagem, lida, data

### DocumentoProfissional
Para validação documental (RF002 — critérios de validação ainda não definidos, ver `foundation/04-decisions-pending.md`).

**Campos**: tipo, arquivo, status

## Relacionamentos

| Origem | Destino | Cardinalidade |
|---|---|---|
| Usuário | Endereço | 1:N |
| Usuário | Solicitação | 1:N |
| Categoria | Solicitação | 1:N |
| Property | Endereço | 1:1 |
| Property | Area | 1:N |
| Property | PropertyOwnership | 1:N |
| Area | Asset | 1:N |
| Asset | Intervention | 1:N |
| Solicitação | Property | N:1 |
| Solicitação | Foto | 1:N |
| Solicitação | Proposta | 1:N |
| Proposta | Serviço | 1:1 (opcional — só existe se aceita) |
| Serviço | PaymentAuthorization | 1:1 |
| Serviço | Garantia | 1:1 |
| Serviço | Avaliação | 1:N (máx. 2 — uma por direção) |
| Serviço | Intervention | 1:N |
| PaymentAuthorization | PaymentEvent | 1:N |
| PaymentEvent (captura) | PaymentSplit | 1:1 |
| PaymentEvent (captura) | PaymentRefund | 1:N |

## Enumerações

Espelho de `02-state-machine.md` — não editar aqui sem editar lá.

**TipoUsuario**: `CLIENTE`, `PROFISSIONAL`, `ADMIN`

**StatusConta**: `PENDENTE_VERIFICACAO`, `ATIVA`, `SUSPENSA`, `BLOQUEADA`, `EXCLUIDA`

**StatusSolicitacao**: `CRIADA`, `ABERTA`, `RECEBENDO_PROPOSTAS`, `CONTRATADA`, `CANCELADA`, `EXPIRADA`

**StatusProposta**: `ENVIADA`, `ACEITA`, `RECUSADA`, `RETIRADA`

> `CANCELADA` removida — `02-state-machine.md` só define `Retirada` (ação do profissional antes do aceite). Recusa automática por aceite de outra proposta é `RECUSADA` (INV-011).

**StatusServico**: `AGENDADO`, `EM_ANDAMENTO`, `AGUARDANDO_APROVACAO`, `APROVADO`, `EM_CONTESTACAO`, `CANCELADO`

> Substituídos `CONCLUIDO`/`FINALIZADO`, que não existiam na state machine. `APROVADO` é o único estado que gera `PaymentEvent` de captura e `Garantia`.

**StatusPaymentAuthorization**: `AUTORIZADO`, `CAPTURADO`, `CANCELADO`, `EXPIRADO`

**TipoPaymentEvent**: `AUTORIZADO`, `CAPTURADO`, `REPASSADO`, `CANCELADO`, `EXPIRADO`, `REEMBOLSADO`

**StatusGarantia**: `ATIVA`, `EXPIRADA`, `ACIONADA`, `ENCERRADA`

> `ENCERRADA` adicionada — faltava para representar a transição final de `Acionada` em `02-state-machine.md` §5.

**OrigemIntervention**: `PLATAFORMA`, `MANUAL`, `IMPORTADO`

## Índices Recomendados

**Usuário**: email (Unique), telefone, tipo

**Solicitação**: cliente_id, categoria_id, property_id, status, criado_em

**Proposta**: profissional_id, solicitacao_id, status, `UNIQUE (solicitacao_id) WHERE status = 'ACEITA'`

**Serviço**: status, proposta_id

**PaymentAuthorization**: servico_id, status, expira_em

**PaymentEvent**: payment_authorization_id, tipo, criado_em

**Property**: endereco_id

**Intervention**: asset_id, servico_id, origem

## Busca geográfica (P0, sem PostGIS — ver `08-planejamento.md`)

"Localizar profissionais próximos" é P0 mas PostGIS é pós-MVP (dívida técnica aceita). Sem índice geoespacial, a única estratégia viável no MVP é bounding box sobre `latitude`/`longitude` de `Endereco` (índice B-tree composto, não `GiST`). **Limitação conhecida e documentada**: não escala bem para alta densidade de profissionais nem para busca por raio preciso — reavaliar antes de qualquer expansão de cidade que dependa disso.

## Regras de Integridade

- Toda solicitação pertence a um cliente e a um property.
- Toda proposta pertence a uma solicitação.
- No máximo uma proposta por solicitação pode estar `ACEITA` (índice parcial, não só validação de aplicação).
- Todo serviço nasce de uma proposta aceita (nunca manual fora do fluxo administrativo auditado).
- Todo serviço possui no máximo uma `PaymentAuthorization`.
- Todo serviço que chega a `APROVADO` gera exatamente uma garantia.
- Toda avaliação pertence a um serviço em `APROVADO`.
- Toda `Intervention` referencia um `Asset`, nunca solto.

## Pendências para Validação

- Um cliente poderá cadastrar vários imóveis no MVP ou apenas um? (Não bloqueia o modelo — `Property`/`PropertyOwnership` já suportam N:N ao longo do tempo.)
- O chat será persistido indefinidamente?
- Fotos serão armazenadas localmente ou em object storage?
- Será permitido múltiplos pagamentos por serviço (parcelamento)? Hoje o modelo assume `Serviço` 1:1 `PaymentAuthorization`.
- Como serão tratadas revisitas durante a garantia?
- Haverá emissão de nota fiscal pela plataforma?
- A contratação poderá envolver mais de um profissional?
- Exclusão LGPD por anonimização (proposta acima) precisa de validação jurídica — mesma pendência de B001/ADR-002.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Versão original (pré-DDD), com `Contratacao`, `Pagamento` simples e `HistoricoImovel`. |
| 2026-08-17 | Reescrita completa contra `00-domain-invariants.md`/`02-state-machine.md`: remove `Contratacao`, introduz bounded context `Payment` (5 entidades), substitui `HistoricoImovel` por `Property/Area/Asset/Intervention`, corrige enums divergentes da state machine, renomeia `Imovel`→`Property` e `endereco_id`→`property_id` em Solicitação, adiciona `PropertyOwnership`, avaliação bidirecional, índice parcial de proposta aceita. |
