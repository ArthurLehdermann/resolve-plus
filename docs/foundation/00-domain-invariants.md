# 00, Domain Invariants

> Regras que **nunca podem ser violadas**, independente de quem implementa (dev humano ou agente de IA) ou de qual módulo está sendo alterado. Este documento é a fonte de verdade quando houver conflito com qualquer outro documento (`foundation/`, `specifications/`, `adr/`). Nasceu da `notas-revisao-arquitetural.md` (2026-08-16) e das decisões arquiteturais tomadas na sequência.

## Como usar este documento

- Toda regra aqui é **invariante de domínio**, não requisito funcional, não muda por prioridade de sprint, não é "Necessita Validação".
- Se um documento downstream (modelo de dados, API, código) contradiz uma regra daqui, o documento downstream está errado.
- Novas regras só entram aqui por decisão explícita do PO, registrada com data.

## 1. Identidade e Conta

- INV-001, Um usuário tem exatamente um `tipo` (`CLIENTE`, `PROFISSIONAL`, `ADMIN`); não há conta híbrida no MVP.
- INV-002, Um profissional só pode receber solicitações enquanto seu `status` de verificação for `ATIVA` (RN001, `02-funcionalidades.md`).
- INV-003, Suspensão ou bloqueio de conta cancela participação em processos em andamento (solicitações abertas, propostas pendentes), mas nunca apaga histórico já consolidado (garantias emitidas, avaliações, pagamentos liberados).

## 2. Solicitação → Proposta

- INV-010, Uma solicitação pode ter várias propostas, mas **no máximo uma pode estar `ACEITA` por vez**.
- INV-011, A aceitação de uma proposta **invalida automaticamente** todas as demais propostas daquela solicitação (transição para `RECUSADA` automática, não silenciosa, gera evento e notificação).
- INV-012, Uma proposta só pode ser aceita enquanto a solicitação estiver em estado que aceite propostas (ver `02-state-machine.md`). Não existe aceite de proposta em solicitação `CANCELADA` ou `EXPIRADA`.
- INV-013, Cliente não pode aceitar proposta em solicitação de outro cliente (ownership).
- INV-014, Uma `Solicitação` só pode ser criada referenciando um `Property` cujo dono vigente (`PropertyOwnership` com `ate IS NULL`) seja o `cliente_id` da solicitação. Ficou implícito e não escrito até 2026-08-17, quando `Property`/`PropertyOwnership` foram introduzidos em `04-modelo-dados.md`, sem esta regra, ownership de Solicitação fica indefinido perante um imóvel que já trocou de dono.

## 3. Contratação (evento, não entidade)

- INV-020, **Não existe entidade `Contratação` persistente no MVP.** A aceitação de proposta é um **evento de domínio** (`ProposalAccepted`), registrado em auditoria e na timeline do serviço, não uma linha em tabela própria.
- INV-021, Um `Serviço` só pode existir a partir de uma proposta aceita. Não há criação manual de serviço fora desse fluxo (exceto ação administrativa, sempre auditada, ver INV-070).
- INV-022, Se, no futuro, surgir necessidade de artefato jurídico próprio (contrato assinado eletronicamente, aditivo, distrato), isso nasce como entidade `Contract` **nova e explícita**, não como reaproveitamento da antiga ideia de `Contratação`. Essa é uma decisão consciente de 2026-08-16 (PO): "só manteria uma entidade Contract se houver documento jurídico, assinatura eletrônica ou artefatos próprios."

## 4. Serviço

- INV-030, Um serviço pertence a exatamente uma solicitação e a exatamente uma proposta aceita.
- INV-031, Um serviço só entra em `CONCLUIDO` após o profissional registrar conclusão **e** o cliente confirmar, ou o prazo de aceite automático (RN010, ainda **Necessita Validação** o valor do prazo) se esgotar sem contestação.
- INV-032, Um serviço `CANCELADO` não gera garantia nem libera pagamento normal (pode gerar reembolso parcial/multa, regras de cancelamento ainda pendentes de definição de negócio).
- INV-033, Um serviço em garantia ativa que sofra nova intervenção pela **mesma causa/escopo coberto** não pode gerar nova cobrança ao cliente (regra herdada da nota de revisão, mantida).

## 5. Financeiro (bounded context `Payment`)

> Decisão de 2026-08-16 (PO): Pagamento deixa de ser modelado como entidade simples 1:1 com Serviço. Vira bounded context próprio, ver `ADR-002-financeiro.md` (modalidade) e `04-decisions-pending.md` (B002, prazo de repasse).

- INV-040, Todo movimento financeiro é um **evento imutável** (`PaymentEvent`), nunca se faz `UPDATE` destrutivo em histórico financeiro, só inserção de novos eventos/estados.
- INV-041, Um pagamento nunca pode ser **liberado** (capturado + repassado) antes da conclusão **e** aprovação do serviço, salvo exceção administrativa, que é sempre registrada em auditoria com usuário responsável e justificativa (RN de `02-funcionalidades.md`, elevada a invariante).
- INV-042, Toda autorização de pagamento (`PaymentAuthorization`) deve ter uma captura, cancelamento ou expiração correspondente, não pode ficar em limbo indefinidamente.
- INV-046, Se uma `PaymentAuthorization` expira antes da conclusão do serviço, o sistema gera uma nova autorização vinculada ao mesmo `Serviço` (evento `REAUTORIZADO`). Um `Serviço` pode ter várias `PaymentAuthorization` ao longo do tempo, mas nunca mais de uma com status `AUTORIZADO` simultaneamente. Sem isso, todo serviço agendado além da janela de autorização do gateway (cartão expira em ~5-7 dias) vira órfão financeiro. Decisão de 2026-08-17, motivada por revisão do PO sobre `04-modelo-dados.md`.
- INV-043, Reembolso (`PaymentRefund`) só é possível sobre valor já capturado, nunca sobre valor apenas autorizado (nesse caso o correto é cancelar a autorização).
- INV-044, Split de comissão (`PaymentSplit`) é calculado no momento da captura, com a alíquota vigente **naquele momento**, mudança futura na comissão não altera splits já calculados.
- INV-045, Uma disputa de pagamento (`PaymentDispute`) bloqueia liberação/repasse até resolução, mas não bloqueia o registro de novos eventos (histórico continua sendo escrito).

> Modalidade financeira decidida (provisório, PO 2026-08-16): **Autorizar → Capturar → Repassar**, não escrow bancário, ver `ADR-002-financeiro.md`. Momento exato do repasse (janela de contestação de 48h ou 72h) permanece **bloqueador**, ver `04-decisions-pending.md` (B002).

## 6. Garantia

- INV-050, Toda conclusão aprovada de serviço gera exatamente uma garantia (RN005).
- INV-051, Garantia tem prazo definido no momento da criação (herdado da proposta aceita); alterar o prazo padrão de uma categoria não afeta garantias já emitidas.
- INV-052, Acionamento de garantia é sempre registrado como evento com evidências (fotos, descrição), nunca como simples mudança de status sem payload.

## 7. Histórico do Imóvel (prontuário)

> Decisão de 2026-08-16 (PO): deixa de ser log raso (`id, serviço, data, categoria, resumo`) e passa a ser modelado como prontuário, ver `04-modelo-dados.md` revisado.

- INV-060, Todo serviço que atinge `APROVADO` gera pelo menos uma `Intervention` no prontuário do imóvel (RN006, atualizada). Não existe estado `FINALIZADO` em `02-state-machine.md`, `APROVADO` é o único estado terminal positivo do Serviço, e é ele que dispara garantia, captura de pagamento e prontuário. (Corrigido em 2026-08-17, a versão anterior desta invariante referenciava um estado que não existe na state machine.)
- INV-061, Uma `Intervention` referencia sempre um `Asset` dentro de uma `Area` do `Property`, nunca é solta, mesmo quando a granularidade de ambiente/item não for capturada no MVP (usar `Area`/`Asset` genéricos "Não especificado" como fallback, nunca omitir o nível).
- INV-062, Todo registro do prontuário carrega uma flag de origem (`origem: PLATAFORMA | MANUAL | IMPORTADO`) e um selo de confiabilidade correspondente, nunca misturado sem distinção com histórico de serviços reais. Modelo híbrido (decisão provisória do PO, 2026-08-16), ver `04-decisions-pending.md` (B004), ainda sem validação final de Produto.

## 8. Auditoria

- INV-070, Toda alteração de estado de qualquer entidade central (Solicitação, Proposta, Serviço, Pagamento, Garantia, Conta) é registrada em auditoria, sem exceção, incluindo ações administrativas manuais.
- INV-071, Registro de auditoria é append-only. Não existe edição ou exclusão de linha de auditoria, nem por admin.

## 9. Comparabilidade de Propostas

- INV-080, Todas as propostas de uma mesma solicitação compartilham o mesmo escopo padronizado (RF013/OBJ-MVP-03), não é permitido ao profissional alterar o escopo na proposta, só valor, prazo, garantia e observações.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Criação do documento a partir de `notas-revisao-arquitetural.md` + decisões do PO sobre Pagamento (bounded context), Contratação (evento) e Histórico do Imóvel (prontuário). |
| 2026-08-17 | Segunda revisão crítica do PO sobre `04-modelo-dados.md`: adiciona INV-014 (ownership de Solicitação via `PropertyOwnership`) e INV-046 (reautorização de pagamento); corrige INV-060 (referenciava estado `FINALIZADO` inexistente). |
