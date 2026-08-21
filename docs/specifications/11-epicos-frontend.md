# 11: Épicos de Frontend (MVP)

> Criado em 2026-08-20. Os épicos de `08-planejamento.md` (Épico 1-7) descrevem fluxo de produto, misturando front e back sem distinguir telas de endpoint. Este documento é o recorte **de entrega de frontend**: duas superfícies (app Flutter, painel Admin web), cada épico com telas, endpoints que consome (`06-apis.md`) e regras de domínio que a UI precisa respeitar (`00-domain-invariants.md`, `02-state-machine.md`). Não inventa tela nova, só decompõe o que já está especificado.
>
> Por que agora: a decisão de produto (2026-08-16) foi propositalmente **não** refinar UI antes do domínio fechar, para não desenhar tela sobre regra que ainda ia mudar. State machine, invariantes, modelo de dados e API estão fechados (com revisões até 2026-08-20). O domínio parou de balançar o suficiente para valer a pena decompor telas agora.

## Convenção

Cada épico traz: **Telas**, **Endpoints** (`06-apis.md`), **Regras de domínio que a UI precisa saber** (não é regra de negócio nova, é o que já está decidido e a tela não pode ignorar ou vai gerar bug de UX), **Prioridade** (`P0`/`P1`/`P2`, mesma escala do Backlog Inicial em `08-planejamento.md`).

## Superfícies

| Superfície | Stack | Papéis | Status da decisão |
|---|---|---|---|
| App mobile | Flutter (ADR-001) | Cliente, Profissional | Decidido |
| Painel Admin | React + Next.js, Shadcn/UI (`05-arquitetura.md`) | Admin | Decidido em `05-arquitetura.md`, mas a mesma pergunta ("integrado ao backend ou app separada?") continua em "Pendências para Validação" no mesmo documento, nunca fechada em ADR. Resolver antes de iniciar F10, senão o setup inicial é retrabalho. |

Nenhuma das duas superfícies tem protótipo de alta fidelidade (Figma) ainda; `08-planejamento.md` lista isso como bloqueador desde 2026-08-16, sem mudança de status. Os épicos abaixo definem **o que** cada tela precisa fazer (telas, dados, regras), não o layout. Design visual é etapa separada, não bloqueia a decomposição em épicos/issues.

---

## App Flutter, Cliente e Profissional

### F1, Fundação & Autenticação (P0)

**Telas**: splash, escolha de tipo de conta (Cliente/Profissional), cadastro, login, recuperar senha, ver/editar perfil, upload de foto.

**Endpoints**: `POST /auth/register|login|forgot-password|reset-password|logout`, `GET/PUT /users/me`, `POST /users/photo`.

**Regras de domínio**: `tipo` de conta é fixo no cadastro (INV-001), não existe tela de "trocar de Cliente para Profissional" depois, CPF que quiser os dois papéis cadastra duas contas. Profissional com `status = PENDENTE_VERIFICACAO` precisa de uma tela de bloqueio ("aguardando verificação") antes de qualquer tela operacional (INV-002, `02-state-machine.md` §6), não é só um badge, é um gate de navegação.

### F2, Onboarding do Profissional / Verificação Documental (P0)

**Telas**: upload por tipo de documento (`IDENTIDADE_FISCAL`, `COMPROVANTE_ENDERECO`, `SELFIE_IDENTIDADE`, `SEGURO_RC`, `CERTIFICADO_NR10` condicional à categoria elétrica), status por slot (Pendente/Aprovado/Rejeitado + motivo), tela de bloqueio enquanto `PENDENTE_VERIFICACAO`.

**Endpoints**: `POST /professionals/documents`.

**Regras de domínio**: matriz de documentos exigidos é por categoria (`04-modelo-dados.md` §DocumentoProfissional), a tela não pode assumir uma lista fixa igual para todo profissional. Reenvio de documento rejeitado é só um novo `POST` do mesmo `tipo` (histórico preservado, `02-state-machine.md` §7), não existe endpoint de "editar" documento. `SEGURO_RC` pode `VENCER` depois de aprovado (B005) e reabrir o bloqueio, a tela de status precisa tratar isso como um estado normal, não só "aprovado uma vez, resolvido para sempre".

### F3, Imóveis (P0, só Cliente)

**Telas**: listar imóveis, cadastrar (endereço + apelido), editar, iniciar transferência de posse, aceitar/recusar transferência recebida.

**Endpoints**: `GET/POST /properties`, `PUT /properties/{id}`, `POST /properties/{id}/transfer`, `GET /property-transfers`, `POST /property-transfers/{id}/accept|decline`.

**Regras de domínio**: não existe tela de excluir imóvel (`DELETE` removido da API, INV-071 proíbe apagar prontuário), a ação do usuário que "não quer mais" o imóvel é transferir posse, a UI não deve nem tentar oferecer exclusão. Cadastro de endereço já existente responde 409 com o `property_id` existente (INV-063); a tela precisa tratar isso como "esse imóvel já existe, quer usá-lo?", não como erro de formulário genérico.

### F4, Solicitação de Serviço (P0, só Cliente)

**Telas**: escolher categoria, formulário dinâmico de escopo, preview de faixa de preço, criar solicitação, upload de fotos, editar solicitação aberta, cancelar antes de contratar.

**Endpoints**: `GET /categories(/{id})`, `POST /requests/estimate`, `POST /requests`, `GET/PUT/DELETE /requests/{id}`, `POST /requests/{id}/photos`.

**Regras de domínio**: o formulário de escopo é **gerado a partir de `template_escopo`** (schema por categoria, INV-080), a tela não pode ter campos hardcoded por categoria, tem que renderizar dinamicamente (`tipo`, `obrigatorio`, `rotulo`, `valores`, `min`). Faixa de preço é sempre um range (min/max), nunca um valor único (`10-motor-precificacao.md`); `PRECO_TABELA_AUSENTE` (422) precisa de uma mensagem própria, não genérica, significa que a cidade/categoria ainda não tem tabela de preço configurada. `PUT` trava o campo `scope` depois da primeira proposta recebida (409, INV-080), a tela de edição precisa desabilitar esse campo especificamente quando isso acontecer, não bloquear a edição inteira.

### F5, Propostas (P0, Cliente e Profissional)

**Telas Cliente**: listar/comparar propostas de uma solicitação (com `trust_level` e nota do profissional), aceitar proposta.
**Telas Profissional**: feed de oportunidades, enviar proposta, retirar proposta antes do aceite.

**Endpoints**: `GET /requests/{id}/proposals`, `POST /requests/{id}/proposals`, `POST /proposals/{id}/accept`, `POST /proposals/{id}/withdraw`.

**Regras de domínio**: aceitar uma proposta recusa automaticamente todas as outras da mesma solicitação (INV-011), a tela de comparação deve refletir isso sem exigir nenhuma ação extra do cliente sobre as propostas recusadas (elas simplesmente saem da lista de "pendentes" depois do aceite, sem toast de erro ou confirmação adicional). O aceite é também onde o cliente escolhe forma de pagamento (Cartão ou Pix, `MetodoPagamento`), essa escolha faz parte da tela de aceite, não de uma etapa separada.

### F6, Execução do Serviço (P0, Cliente e Profissional)

**Telas**: agenda (ver, reagendar), chat do serviço, "iniciar serviço" (Profissional), registrar conclusão com evidências (Profissional), aprovar ou contestar conclusão (Cliente).

**Endpoints**: `GET/POST /schedule`, `PUT /schedule/{id}`, `GET/POST /services/{id}/messages`, `POST /services/{id}/start|finish|approve|contest`.

**Regras de domínio**: `POST /services/{id}/start` pode devolver 409 especificamente porque o Pix ainda não foi confirmado (INV-048, `foundation/02-state-machine.md` §4a-Pix), isso precisa de uma mensagem de erro própria ("pagamento ainda não confirmado", não um 409 genérico), porque não é algo que o profissional resolve sozinho na tela, é esperar a confirmação do cliente. A janela de aceite automático (`AUTO_APPROVAL_HOURS` = 72h) deveria aparecer como contador regressivo na tela de "aguardando aprovação" do profissional, é o prazo que determina quando ele recebe mesmo sem ação do cliente.

### F7, Cancelamento & Disputas (P0, Cliente inicia; ambos acompanham)

**Telas**: cancelar serviço (mostrar a multa calculada antes de confirmar), tela de disputa aberta (status, aguardando resolução do Admin), notificação de resultado.

**Endpoints**: `POST /services/{id}/cancel`, leitura de status via `GET /services/{id}`.

**Regras de domínio**: cancelamento em `Agendado` (Cenário B) tem multa decrescente por antecedência (10/25/50%, `foundation/03-cancellation-rules.md`); qualquer cálculo mostrado antes de confirmar é só **preview**, o valor definitivo vem da resposta do backend, a tela não pode assumir que o preview bate 100% (arredondamento, mudança de tabela de configuração entre o preview e a confirmação). Cancelamento em `Em Andamento` (Cenário C) **não cancela na hora**, abre uma disputa, a tela não pode dizer "cancelado" nesse caminho, o texto certo é algo como "pedido enviado, aguardando mediação".

### F8, Pagamentos, Garantia e Avaliação (P0/P1, principalmente Cliente)

**Telas**: histórico de pagamentos por serviço, extrato de eventos, acionar garantia com evidências, avaliar o profissional após aprovação.

**Endpoints**: `GET /payments`, `GET /payments/{id}`, `GET /payments/{id}/events`, `GET /warranties(/{id})`, `POST /warranties/{id}/claim`, `POST /services/{id}/rating`.

**Regras de domínio**: um pagamento Pix em `PENDENTE` é estado normal por até `PIX_EXPIRATION_HOURS` (24h default), a tela deve mostrar "aguardando confirmação do pagamento", não tratar como falha (INV-047). Garantia só existe para acionar quando `Ativa` (dentro do prazo, `02-state-machine.md` §5), a tela precisa checar isso antes de oferecer o botão de acionamento, não só reagir ao erro do backend.

### F9, Histórico do Imóvel / Prontuário (P1, Cliente)

**Telas**: timeline de intervenções por imóvel.

**Endpoints**: `GET /properties/{id}/history`.

**Regras de domínio**: cada registro carrega `origem` (`PLATAFORMA | MANUAL | IMPORTADO`, INV-062) com selo de confiabilidade próprio, a UI precisa diferenciar visualmente os três, nunca misturar sem distinção (é o requisito que dá valor ao prontuário como diferencial competitivo). **Bloqueador**: não existe endpoint de criação de registro `MANUAL` na API hoje, o modelo híbrido (B004, `foundation/04-decisions-pending.md`) ainda não tem validação final de Produto nem rota implementada, a parte da tela que permitiria o cliente adicionar um registro manual não tem o que consumir ainda, escopo desse pedaço fica em aberto até B004 fechar.

---

## Painel Admin (React + Next.js)

### F10, Painel Administrativo (P0)

**Telas**: dashboard, gestão de categorias (CRUD), revisão de documentos de profissionais (aprovar/rejeitar com motivo), lista de usuários, lista de serviços, lista de pagamentos, resolução de disputas, liberação manual de pagamento.

**Endpoints**: `GET /admin/dashboard`, `GET/POST/GET/PUT/DELETE /admin/categories`, `PATCH /admin/professionals/documents/{id}/review`, `GET /admin/users|services|payments`, `PUT /disputes/{id}/resolve`, `POST /payments/{id}/release`.

**Regras de domínio**: resolução de disputa exige `justificativa` (INV-070), campo obrigatório na tela, não opcional, o backend rejeita sem isso (422). Liberação manual de pagamento é ação **fora do fluxo automático** (INV-041 exige justificativa e responsável em auditoria); a tela precisa de confirmação explícita e deixar claro que é uma exceção administrativa, não uma ação de rotina, não usar o mesmo padrão de botão/confirmação das outras ações de listagem.

**Pendência que bloqueia o início deste épico**: `05-arquitetura.md` decide "Web Admin: React + Next.js" na seção de Stack, mas a mesma pergunta ("painel integrado ao backend ou aplicação separada?") continua na lista de "Pendências para Validação" do mesmo documento, e não existe ADR próprio para o Admin (`ADR-001-stack.md` só cobre mobile/backend/infra). Fechar isso antes de abrir issues de F10, senão o setup inicial do painel é retrabalho se a resposta for "integrado".

---

## O que fica de fora deste recorte

- Notificações push: `08-planejamento.md` classifica como Pós-MVP (V1), não é épico de frontend do MVP.
- Protótipos visuais (Figma, paleta, tipografia, design system aplicado): etapa de design, não de decomposição de épico. Ver skill `design-ui` quando a etapa começar.
- Admin como persona: `01-visao-geral.md` §6 (Personas) só define Cliente e Profissional (PERSONA-01/02/03); Admin/PO opera o painel sem persona formal documentada, consistente com `foundation/03-cancellation-rules.md` ("Admin/PO opera mediação no MVP").

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-20 | Criação do documento: decompõe os Épicos 1-7 de `08-planejamento.md` em telas de frontend (Flutter + Admin), cada épico ancorado em endpoints reais (`06-apis.md`) e invariantes/estados que a UI precisa respeitar. Identifica dois bloqueadores: arquitetura do painel Admin (integrado × app separada, sem ADR) e ausência de rota para registro `MANUAL` no prontuário (B004). |
