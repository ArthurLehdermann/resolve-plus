# 03 — Domain Rules

> v0.1 — "Kit para IA": regras de negócio explícitas, em linguagem direta, pensadas para orientar geração de código assistida por IA (substitui a ideia original de `13-prompts-ia.md`, ver `foundation/notas-revisao-arquitetural.md`). Cada regra referencia sua origem (`INV-xxx` em `00-domain-invariants.md` ou `RNxxx` em `specifications/02-funcionalidades.md`) — este documento não cria regra nova, ele **traduz** para uso operacional.

## Como usar

Ao gerar ou revisar código (models, validações, casos de uso), verifique contra esta lista antes de assumir uma regra "óbvia". Se o código implementar algo que contradiz uma linha aqui, o código está errado, não o documento — salvo decisão explícita do PO com changelog.

## Solicitação e Proposta

- Uma solicitação pode ter várias propostas, mas nunca mais de uma `ACEITA` simultaneamente (INV-010).
- Aceitar uma proposta recusa automaticamente todas as outras da mesma solicitação — isso gera evento e notificação, nunca é silencioso (INV-011).
- Só é possível aceitar proposta se a solicitação estiver `Aberta` ou `Recebendo Propostas` (INV-012, `02-state-machine.md`).
- Cliente só aceita proposta de solicitação que ele mesmo criou (INV-013, ownership).
- Todas as propostas de uma mesma solicitação compartilham o escopo padronizado da solicitação — profissional não altera escopo na proposta, só valor, prazo, garantia e observações (INV-080, RF013).
- Apenas profissionais com `status = ATIVA` recebem novas solicitações (INV-002, RN001).

## Contratação / Serviço

- Não existe entidade `Contratação` no MVP. A aceitação de proposta é o evento `PropostaAceita`, registrado em auditoria — não uma linha de tabela própria (INV-020).
- Um `Serviço` só nasce de uma proposta aceita, nunca é criado manualmente fora desse fluxo, exceto ação administrativa auditada (INV-021, INV-070).
- Um serviço só é `Aprovado`/`Concluído` por confirmação explícita do cliente **ou** pelo esgotamento do prazo de aceite automático (RN010, prazo pendente — B002).
- Serviço `Cancelado` nunca gera garantia nem libera pagamento normal (INV-032).
- Um serviço em garantia ativa, se sofrer nova intervenção pela mesma causa/escopo coberto, não gera nova cobrança ao cliente (INV-033).

## Financeiro

- Todo movimento financeiro é um evento imutável (`PaymentEvent`); nunca há `UPDATE` destrutivo em histórico financeiro (INV-040).
- Pagamento só é capturado + repassado depois de o serviço estar concluído **e** aprovado — exceção administrativa sempre auditada com usuário responsável e justificativa (INV-041).
- Toda autorização de pagamento termina em captura, cancelamento ou expiração — nunca fica pendente indefinidamente (INV-042).
- Reembolso só é possível sobre valor já capturado; sobre valor apenas autorizado, o correto é cancelar a autorização, não reembolsar (INV-043).
- Split de comissão é calculado no momento da captura, com a alíquota vigente **naquele momento** — mudança futura de comissão não altera splits já calculados (INV-044).
- Disputa de pagamento bloqueia liberação/repasse, mas não impede o registro de novos eventos no histórico (INV-045).
- Modalidade é Autorizar → Capturar → Repassar, não escrow bancário (decisão provisória do PO, ver `adr/ADR-002-financeiro.md`).

## Garantia

- Toda conclusão aprovada de serviço gera exatamente uma garantia, nunca zero, nunca mais de uma (INV-050, RN005).
- O prazo da garantia é fixado no momento da criação, herdado da proposta aceita — mudar o prazo padrão de uma categoria não afeta garantias já emitidas (INV-051).
- Acionar garantia sempre exige evidências (fotos, descrição) — nunca é uma simples mudança de status sem payload (INV-052).
- Responsável por resolver acionamento de garantia: pendente de definição jurídica (B001, recomendação registrada é modelo compartilhado).

## Histórico do Imóvel (prontuário)

- Todo serviço `Aprovado`/`Finalizado` gera pelo menos uma `Intervention` no prontuário (INV-060, RN006).
- Uma `Intervention` sempre referencia um `Asset` dentro de uma `Area` de um `Property` — nunca fica solta, mesmo quando a granularidade não é capturada no MVP (usar "Não especificado" como fallback, nunca omitir o nível) (INV-061).
- Todo registro do prontuário carrega `origem: PLATAFORMA | MANUAL | IMPORTADO` com selo de confiabilidade próprio — modelo híbrido provisório (INV-062, B004).

## Auditoria

- Toda alteração de estado de Solicitação, Proposta, Serviço, Pagamento, Garantia ou Conta é registrada em auditoria, sem exceção, incluindo ações administrativas (INV-070).
- Registro de auditoria é append-only — não existe edição nem exclusão, nem por admin (INV-071).

## Conta

- Um usuário tem exatamente um `tipo` (`CLIENTE`, `PROFISSIONAL`, `ADMIN`) — não há conta híbrida no MVP (INV-001).
- Suspender ou bloquear conta cancela participação em processos em andamento, mas nunca apaga histórico já consolidado (garantias emitidas, avaliações, pagamentos liberados) (INV-003).

## Regras ainda sem invariante formal (aguardando B001–B004)

- Política de cancelamento (quem, até quando, multa, estorno parcial) — B003.
- Prazo exato do aceite automático / janela de contestação — B002.
- Responsável legal pela garantia — B001.
- Fluxo de mediação de disputa (`Em Contestação → ?` na state machine) — B003.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | v0.1 — criado a partir de `00-domain-invariants.md` e `specifications/02-funcionalidades.md`. |
