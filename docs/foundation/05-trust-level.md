# 05: Nível de Confiança do Profissional

> Decisão de produto registrada em 2026-08-17 para fechar a issue #4. Define limiares concretos de progressão entre os cinco níveis citados no glossário de `specifications/01-visao-geral.md`, modela a entidade `PerfilProfissional` em `specifications/04-modelo-dados.md` e amarra o recálculo aos eventos de `01-event-storm.md`. RN007/RN008/RN026 em `specifications/02-funcionalidades.md` passam a apontar para este documento.

## Níveis (ordem crescente)

| Ordem | Nível | Significado para o cliente |
|---|---|---|
| 1 | `VERIFICADO` | Identidade e documentação validadas pela plataforma; ainda sem histórico operacional relevante. |
| 2 | `BRONZE` | Histórico inicial positivo; pode ser contratado com confiança básica. |
| 3 | `PRATA` | Volume e avaliações consistentes; cancelamentos controlados. |
| 4 | `OURO` | Histórico sólido e baixa taxa de problemas. |
| 5 | `ELITE` | Referência na plataforma; critérios mais exigentes em todos os eixos. |

Todo profissional recém-verificado (`ProfissionalVerificado`) entra em `VERIFICADO`. Não existe nível abaixo disso enquanto `Usuario.status = ATIVA`.

## Métricas (campos cacheados em `PerfilProfissional`)

| Métrica | Campo | Como calcular |
|---|---|---|
| Serviços concluídos | `servicos_aprovados` | Contagem de `Serviço` do profissional em `APROVADO`. |
| Nota média | `nota_media_dez` | Média aritmética das avaliações `CLIENTE_AVALIA_PROFISSIONAL` (nota 1–5), armazenada em décimos (`45` = 4,5). `NULL` se ainda não houver avaliação. |
| Taxa de cancelamento | `taxa_cancelamento_pct` | `cancelamentos_imputaveis / servicos_iniciados * 100`, arredondado para inteiro 0–100. Ver definições abaixo. |
| Reclamações recentes | `reclamacoes_12m` | Contagem rolling de reclamações nos últimos 365 dias. Ver definição abaixo. |
| Tempo de conta | (derivado) | Dias corridos desde `Usuario.criado_em` até `now()` no momento do recálculo. Não é persistido. |

**`servicos_iniciados`**: todo `Serviço` do profissional que já saiu de `Agendado` (passou por `EM_ANDAMENTO`, `AGUARDANDO_APROVACAO`, `APROVADO`, `EM_CONTESTACAO` ou `CANCELADO` após aceite da proposta).

**`cancelamentos_imputaveis`**: `Serviço` `CANCELADO` quando (a) o cancelamento partiu do profissional em `Agendado` (Cenário B, `03-cancellation-rules.md`), ou (b) disputa/contestação (`EM_CONTESTACAO` / `PaymentDispute`) foi `RESOLVIDA` com desfecho desfavorável ao profissional.

**Reclamação** (incrementa `reclamacoes_12m` enquanto dentro da janela de 365 dias): qualquer um dos eventos abaixo atribuível ao profissional:

1. `ServicoContestado` seguido de resolução desfavorável ao profissional.
2. `PaymentDispute` `RESOLVIDA` com desfecho desfavorável ao profissional.
3. `GarantiaAcionada` sobre serviço daquele profissional (conta como reclamação para reincidência, mesmo que a garantia ainda esteja em mediação).

## Limiares de progressão

O nível efetivo é o **maior** nível cujos limiares o profissional **simultaneamente** atende. Se deixar de atender o limiar do nível atual, **rebaixa** para o maior nível ainda elegível (nunca abaixo de `VERIFICADO` com conta `ATIVA`).

| Nível | `servicos_aprovados` (mín.) | `nota_media_dez` (mín.) | `taxa_cancelamento_pct` (máx.) | Tempo de conta (mín.) | `reclamacoes_12m` (máx.) |
|---|---:|---:|---:|---:|---:|
| `VERIFICADO` | 0 | (sem mínimo) | (sem máximo) | 0 dias | (sem máximo) |
| `BRONZE` | 3 | 40 (4,0) | 20 | 30 dias | 1 |
| `PRATA` | 10 | 43 (4,3) | 15 | 90 dias | 0 |
| `OURO` | 25 | 45 (4,5) | 10 | 180 dias | 0 |
| `ELITE` | 50 | 47 (4,7) | 5 | 365 dias | 0 |

Regras auxiliares:

- Sem avaliação (`nota_media_dez IS NULL`): só pode permanecer em `VERIFICADO`; não promove para `BRONZE` ou acima até existir ao menos 1 avaliação `CLIENTE_AVALIA_PROFISSIONAL`.
- `servicos_iniciados = 0`: `taxa_cancelamento_pct` permanece `0`.
- Conta `SUSPENSA` ou `BLOQUEADA`: congela exibição do badge no último nível calculado, mas não recalcula promoção enquanto suspensa.

## Onde aparece para o cliente

| Superfície | Comportamento |
|---|---|
| Perfil do profissional | Badge com `nivel_confianca` + `nota_media_dez` (RF026, RN026). |
| Lista de propostas (`GET /requests/{id}/proposals`) | Cada proposta inclui `professional.trust_level` e `professional.average_rating`. |
| Busca/distribuição de oportunidades (RF010) | Ordenação secundária após proximidade geográfica: `nivel_confianca` DESC, depois `nota_media_dez` DESC (empate: mais antigo na plataforma primeiro). Profissionais `SUSPENSA`/`BLOQUEADA` ficam de fora (INV-002). |

## Eventos que disparam recálculo

Processamento **assíncrono** (fila), idempotente por `(profissional_id, evento_id)`:

| Evento | Efeito no recálculo |
|---|---|
| `ProfissionalVerificado` | Cria `PerfilProfissional` com `nivel_confianca = VERIFICADO`, contadores zerados. |
| `ServicoAprovado` | Incrementa `servicos_aprovados`; reavalia elegibilidade de nível. |
| `AvaliacaoRegistrada` | Recalcula `nota_media_dez` quando `direcao = CLIENTE_AVALIA_PROFISSIONAL`. |
| `ServicoCancelado` | Recalcula `taxa_cancelamento_pct`; pode rebaixar. |
| `ServicoContestado` | Marca serviço para revisão; recálculo completo após resolução. |
| `DisputaResolvida` | Atualiza `taxa_cancelamento_pct` e/ou `reclamacoes_12m` conforme desfecho; reavalia nível. |
| `GarantiaAcionada` | Incrementa `reclamacoes_12m`; pode rebaixar. |
| Job diário `RecalcularNivelConfianca` | Reprocessa profissionais cujo tempo de conta cruzou um limiar (ex.: completou 30/90/180/365 dias) sem outro evento no intervalo. |

Policy **P8** em `01-event-storm.md` consolida a reação: todo evento acima enfileira `RecalcularPerfilConfianca(profissional_id)`.

## Impacto em regras existentes

- **RN007**: reputação materializada como `PerfilProfissional.nivel_confianca`, não campo solto em `Usuario`.
- **RN008**: cancelamentos imputáveis entram em `taxa_cancelamento_pct` e podem rebaixar o nível (limiares da tabela acima).
- **RN026**: badge no perfil e ordenação em RF010/RN007, conforme seção "Onde aparece".

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 | Criado para fechar issue #4: limiares por nível, entidade `PerfilProfissional`, gatilhos de recálculo e superfícies de exibição ao cliente. |
