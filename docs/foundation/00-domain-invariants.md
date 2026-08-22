# 00: Domain Invariants

> Regras que **nunca podem ser violadas**, independente de quem implementa (dev humano ou agente de IA) ou de qual módulo está sendo alterado. Este documento é a fonte de verdade quando houver conflito com qualquer outro documento (`foundation/`, `specifications/`, `adr/`). Nasceu da `notas-revisao-arquitetural.md` (2026-08-16) e das decisões arquiteturais tomadas na sequência.

## Como usar este documento

- Toda regra aqui é **invariante de domínio**, não requisito funcional, não muda por prioridade de sprint, não é "Necessita Validação".
- Se um documento downstream (modelo de dados, API, código) contradiz uma regra daqui, o documento downstream está errado.
- Novas regras só entram aqui por decisão explícita do PO, registrada com data.

## 1. Identidade e Conta

- INV-001, Um usuário tem exatamente um `tipo` (`CLIENTE`, `PROFISSIONAL`, `ADMIN`); não há conta híbrida no MVP.
- INV-002, Um profissional pode **visualizar** solicitações abertas compatíveis com suas categorias independente do `status` de verificação (inclusive `PENDENTE_VERIFICACAO`), mas só pode **enviar proposta** enquanto seu `status` for `ATIVA` (RN001, `02-funcionalidades.md`). Decisão do PO em 2026-08-22: antes o bloqueio de `ATIVA` cobria visualização e envio juntos; separado porque negar a visualização também tira do profissional a informação de que vale a pena terminar a verificação (não havia demanda visível pra ele decidir isso).
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
- INV-031, Um serviço só entra em `APROVADO` após o profissional registrar conclusão **e** o cliente confirmar, ou o prazo de aceite automático (RN010, `AUTO_APPROVAL_HOURS` = 72h, `adr/ADR-004-prazo-aceite-automatico.md`) se esgotar sem contestação. (Corrigido em 2026-08-17, mesma classe de bug de INV-060: referenciava `CONCLUIDO`, estado que não existe em `02-state-machine.md`.)
- INV-032, Um serviço `CANCELADO` não gera garantia nem libera pagamento normal (pode gerar captura parcial de multa no Cenário B, `foundation/03-cancellation-rules.md`, B003).
- INV-033, Um serviço em garantia ativa que sofra nova intervenção pela **mesma causa/escopo coberto** não pode gerar nova cobrança ao cliente (regra herdada da nota de revisão, mantida).

## 5. Financeiro (bounded context `Payment`)

> Decisão de 2026-08-16 (PO): Pagamento deixa de ser modelado como entidade simples 1:1 com Serviço. Vira bounded context próprio, ver `ADR-002-financeiro.md` (modalidade), `adr/ADR-004-prazo-aceite-automatico.md` (prazo de repasse, resolvido) e `adr/ADR-005-gateway-pagamento.md` (Asaas e Pix no MVP, B006).

- INV-040, Todo movimento financeiro é um **evento imutável** (`PaymentEvent`), nunca se faz `UPDATE` destrutivo em histórico financeiro, só inserção de novos eventos/estados.
- INV-041, Um pagamento do **serviço executado** nunca pode ser **repassado** ao profissional antes da conclusão **e** aprovação do serviço, salvo exceção administrativa, que é sempre registrada em auditoria com usuário responsável e justificativa (RN de `02-funcionalidades.md`, elevada a invariante). Exceções que **não** violam esta regra: (1) Pix confirmado (`metodo = PIX` nasce `PENDENTE` no aceite da proposta e só vira `CAPTURADO` quando o webhook do Asaas confirma o pagamento, INV-047, revisado em 2026-08-20): o dinheiro permanece no gateway até o evento `REPASSADO`; (2) Cenário B de cancelamento (`foundation/03-cancellation-rules.md`, B003): captura/retenção **somente da multa** (`PaymentEvent CAPTURADO` parcial no cartão, ou `PaymentRefund` parcial no Pix) e o `REPASSADO` da parcela do profissional **sobre essa multa**, sem serviço `APROVADO`. Cartão no fluxo normal permanece autorizar → capturar após aprovação. O parêntese antigo "capturado + repassado" descrevia só o cartão no caminho feliz.
- INV-042, Toda autorização de pagamento (`PaymentAuthorization`) deve ter uma captura, cancelamento ou expiração correspondente, não pode ficar em limbo indefinidamente.
- INV-046, Se uma `PaymentAuthorization` expira antes da conclusão do serviço, o sistema gera uma nova autorização vinculada ao mesmo `Serviço` (evento `REAUTORIZADO`). Um `Serviço` pode ter várias `PaymentAuthorization` ao longo do tempo, mas nunca mais de uma com status `AUTORIZADO` simultaneamente. Sem isso, todo serviço agendado além da janela de autorização do gateway (cartão expira em ~5-7 dias) vira órfão financeiro. Decisão de 2026-08-17, motivada por revisão do PO sobre `04-modelo-dados.md`.
- INV-043, Reembolso (`PaymentRefund`) só é possível sobre valor já capturado, nunca sobre valor apenas autorizado (nesse caso o correto é cancelar a autorização).
- INV-044, Split de comissão (`PaymentSplit`) é calculado no momento da captura, com a alíquota vigente **naquele momento**, mudança futura na comissão não altera splits já calculados.
- INV-045, Uma disputa de pagamento (`PaymentDispute`) bloqueia liberação/repasse até resolução, mas não bloqueia o registro de novos eventos (histórico continua sendo escrito).
- INV-047, Uma `PaymentAuthorization` de Pix nasce com status `PENDENTE`, não `CAPTURADO`: a cobrança Pix no Asaas não confirma na hora, só via webhook (`App\Payments\Webhooks\HandleAsaasWebhook`, idempotente por `gateway_event_id` UNIQUE em `PaymentWebhookEvent`, `04-modelo-dados.md`). `StatusPaymentAuthorization` tem **5** valores, não 4: `AUTORIZADO | PENDENTE | CAPTURADO | CANCELADO | EXPIRADO`. Corrige a modelagem original desta seção (2026-08-16/17), que assumia captura imediata de Pix; a implementação (2026-08-20) mostrou que gravar `CAPTURADO` direto na criação fabrica um pagamento que pode nunca ter acontecido, a plataforma repassaria dinheiro próprio ao profissional. Um webhook que confirma o pagamento **depois** da autorização já ter sido marcada `EXPIRADO`/`CANCELADO` pelo job de expiração (corrida, INV-049) não é descartado como no-op: reconstrói o `CAPTURADO` retroativo e registra `PaymentRefund` integral pendente de execução manual, sempre como `Log::error` (incidente), nunca em silêncio.
- INV-048, Um `Serviço` só pode sair de `Agendado` para `Em Andamento` (`StartService`) se a `PaymentAuthorization` mais recente estiver em `CAPTURADO` ou `AUTORIZADO`; `PENDENTE` (Pix não confirmado) bloqueia o início, e ausência de qualquer autorização é tratada como incidente (`Log::error`, INV-070), nunca silêncio. Sem este gate, o profissional inicia e conclui o serviço, garantia é emitida e o prontuário é gravado com um pagamento que nunca foi confirmado, só descoberto depois no repasse, quando já é tarde. Adicionado em 2026-08-20.
- INV-049, Uma `PaymentAuthorization` de Pix `PENDENTE` que não é confirmada em `PIX_EXPIRATION_HOURS` (`Configuração`, default 24h) expira via job horário (`ExpirePendingPixPayments`), que **consulta o status real no gateway antes de decidir** em vez de confiar só no estado local: `PENDING` no Asaas cancela a cobrança e expira a autorização; `CONFIRMED`/`RECEIVED` confirma direto (o pagamento aconteceu, mesmo que o webhook ainda não tenha chegado) em vez de expirar por cima de um pagamento real. Falha na consulta ou no cancelamento aborta a autorização naquele ciclo (mantém `PENDENTE`, tenta de novo na hora seguinte), nunca assume o pior caso e segue em frente. Adicionado em 2026-08-20 (mesma classe de risco de INV-041/047: dinheiro do cliente sumindo sem serviço nem reembolso).

> Modalidade financeira decidida (provisório, PO 2026-08-16): **Autorizar → Capturar → Repassar** no cartão, não escrow bancário, ver `ADR-002-financeiro.md`. Pix no MVP nasce `PENDENTE`, confirma via webhook para `CAPTURADO` (INV-047, corrigido em 2026-08-20), e fica retido no Asaas até `REPASSADO`, ver `adr/ADR-005-gateway-pagamento.md` (B006). Momento exato do repasse é janela de 72h (`AUTO_APPROVAL_HOURS`), decidido em `adr/ADR-004-prazo-aceite-automatico.md` (B002, resolvido provisoriamente).

## 6. Garantia

- INV-050, Toda conclusão aprovada de serviço gera exatamente uma garantia (RN005).
- INV-051, Garantia tem prazo definido no momento da criação (herdado da proposta aceita); alterar o prazo padrão de uma categoria não afeta garantias já emitidas.
- INV-052, Acionamento de garantia é sempre registrado como evento com evidências (fotos, descrição), nunca como simples mudança de status sem payload.
- INV-053, A responsabilidade financeira pela `Garantia` é do **profissional** (Modelo B, `adr/ADR-003-garantia.md`, decisão provisória de B001 em 2026-08-17): a plataforma media o acionamento, mas não retém nenhuma fração do repasse nem gera `PaymentEvent`/`PaymentRefund` da própria plataforma quando uma garantia é acionada. Substitui a versão anterior desta invariante, que propunha reter uma fração do repasse (`valor_reserva_garantia`), descartada por reintroduzir o enquadramento de escrow que `adr/ADR-002-financeiro.md` rejeitou. Decisão provisória, sem parecer jurídico definitivo, B001 continua bloqueado para isso.

## 7. Histórico do Imóvel (prontuário)

> Decisão de 2026-08-16 (PO): deixa de ser log raso (`id, serviço, data, categoria, resumo`) e passa a ser modelado como prontuário, ver `04-modelo-dados.md` revisado.

- INV-060, Todo serviço que atinge `APROVADO` gera pelo menos uma `Intervention` no prontuário do imóvel (RN006, atualizada). Não existe estado `FINALIZADO` em `02-state-machine.md`, `APROVADO` é o único estado terminal positivo do Serviço, e é ele que dispara garantia, captura **integral** de cartão (Pix já foi capturado no aceite, `adr/ADR-005-gateway-pagamento.md`) e prontuário. Captura **parcial** de multa no Cenário B (`CANCELADO`, INV-032/INV-041) não gera garantia nem `Intervention`. (Corrigido em 2026-08-17, a versão anterior desta invariante referenciava um estado que não existe na state machine.)
- INV-061, Uma `Intervention` referencia sempre um `Asset` dentro de uma `Area` do `Property`, nunca é solta, mesmo quando a granularidade de ambiente/item não for capturada no MVP (usar `Area`/`Asset` genéricos "Não especificado" como fallback, nunca omitir o nível).
- INV-062, Todo registro do prontuário carrega uma flag de origem (`origem: PLATAFORMA | MANUAL | IMPORTADO`) e um selo de confiabilidade correspondente, nunca misturado sem distinção com histórico de serviços reais. Modelo híbrido (decisão provisória do PO, 2026-08-16), ver `04-decisions-pending.md` (B004), ainda sem validação final de Produto.
- INV-063, Um `Property` é identificado unicamente por CEP + número + complemento normalizados (`chave_endereco`, `04-modelo-dados.md`), não pode existir mais de um registro de `Property` para o mesmo endereço físico. Sem isso, o prontuário (diferencial competitivo declarado em OBJ-NEG-02) fragmenta entre registros duplicados da mesma casa. Adicionada em 2026-08-17, identificada em terceira revisão crítica do PO.
- INV-064, Transferência de posse de um `Property` (`PropertyOwnership`) nunca é unilateral: exige aceite explícito do novo dono via `PropertyOwnershipTransfer` (`04-modelo-dados.md`). O dono atual só pode iniciar a transferência; fechar o `PropertyOwnership` anterior e abrir o novo só acontece após o aceite. Adicionada em 2026-08-17, sem isso `PropertyOwnership` existia na modelagem mas não tinha nenhum caminho executável (nenhum endpoint escrevia nela).

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
| 2026-08-17 | Terceira revisão crítica do PO (rodada de `scripts/check-docs.sh`): corrige INV-031, mesma classe de bug de INV-060, referenciava `CONCLUIDO`, estado inexistente. |
| 2026-08-17 | Adiciona INV-063 (identidade de Property por endereço normalizado) e INV-064 (transferência de posse exige aceite, não é unilateral). |
| 2026-08-17 | Adiciona INV-053 (garantia precisa de lastro financeiro) e o bloqueador de percentual de reserva em `04-decisions-pending.md` (depois fundido em B001, ver linha seguinte). |
| 2026-08-17 | Quarta revisão crítica do PO: INV-053 reescrita, mecanismo concreto (retenção do repasse) rebaixado de invariante fechada para proposta pendente de parecer jurídico, reabre ADR-002 e funde o bloqueador de percentual de reserva em B001 (mesmo parecer). |
| 2026-08-17 | Quinta revisão do PO: B002 resolvido provisoriamente (72h, `AUTO_APPROVAL_HOURS`, `adr/ADR-004-prazo-aceite-automatico.md`), INV-031 deixa de citar "Necessita Validação". B001 resolvido provisoriamente para permitir desenvolvimento (garantia é do profissional, plataforma só media, sem retenção sobre o repasse), INV-053 reescrita, mecanismo de reserva sobre `PaymentSplit` removido do modelo de dados até (se algum dia) o parecer jurídico mudar essa decisão provisória. |
| 2026-08-17 | B006 (`adr/ADR-005-gateway-pagamento.md`): INV-041 distingue **repasse** (nunca antes da aprovação do serviço executado) de captura imediata de Pix; INV-060 deixa de tratar captura de Pix como gatilho de `APROVADO`. |
| 2026-08-17 | B003: INV-041 passa a registrar a exceção de captura/repasse da **multa** no Cenário B (sem `APROVADO`); INV-032 e INV-060 alinhados. |
| 2026-08-20 | Divergência entre a decisão registrada (ADR-005: "Pix nasce `CAPTURADO`") e a implementação real corrigida no código (N1/N5/N9, `AsaasPaymentGateway`/`HandleAsaasWebhook`/`ExpirePendingPixPayments`): grava `CAPTURADO` sem confirmação do gateway fabricava pagamento que podia nunca ter acontecido. Adiciona INV-047 (Pix nasce `PENDENTE`, status ganha 5º valor), INV-048 (gate de início de serviço por pagamento confirmado, `StartService`) e INV-049 (expiração de Pix pendente consulta o gateway antes de decidir, corrige corrida entre expiração e confirmação); reescreve a exceção (1) de INV-041. Nenhuma invariante pré-existente foi revogada, só a premissa de captura imediata de Pix, que nunca chegou a ser implementada como tal. |
| 2026-08-22 | Decisão do PO: INV-002 separa visualização (livre, qualquer status) de envio de proposta (exige `ATIVA`); antes o gate de `ATIVA` cobria as duas coisas juntas e o profissional não conseguia ver se havia demanda pra ele antes de terminar a verificação. `GET /requests/available` implementado sem gate de status (só `tipo = PROFISSIONAL`); `POST /requests/{id}/proposals` mantém o gate de `ATIVA` que já existia (RN001).
