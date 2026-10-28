# 05: Arquitetura do MVP

## Objetivo

Construir um MVP simples, de baixo custo, mas preparado para crescimento sem necessidade de reescrita.

Arquitetura recomendada: **Monólito Modular**, evitando microsserviços no início.

## Arquitetura Geral

```
                 Mobile App / Web App
                        │
                    HTTPS / REST
                        │
                 API Backend (Monólito)
                        │
 ┌──────────────┬───────────────┬───────────────┐
 │              │               │               │
PostgreSQL   Redis         Object Storage   Serviços Externos
```

**Justificativa**: menor custo operacional, deploy simples, baixa complexidade, evolução gradual para microsserviços quando necessário.

## Stack Recomendada

> Stack decidida em `adr/ADR-001-stack.md` (Status: Decidido, 2026-08-16). O que estava aqui como "recomendação"/"alternativa" já é decisão fechada, mantido como referência de motivação, não como pergunta em aberto.

### Frontend

- Flutter (Android/iOS com uma base de código), decisão ADR-001.
- Web Admin: React + Next.js
- UI: Material 3 ou Shadcn/UI (Admin)

### Backend

Laravel 12, PHP 8.4, Laravel Octane (quando necessário), decisão ADR-001.

**Motivo da escolha**: Laravel oferece excelente produtividade para CRUDs, autenticação, filas, notificações e integrações, acelerando o MVP.

### Banco de Dados

PostgreSQL. Motivos: excelente suporte geográfico (PostGIS futuramente), JSON nativo, alta confiabilidade, escalável.

### Cache

Redis, usado para: sessões, cache de consultas, rate limit, filas, notificações.

### Storage

Object Storage (S3 compatível), para armazenar fotos, documentos, evidências, avatares. Disco padrão via `OBJECT_STORAGE_DISK` + `AWS_*` (`.env.example`); credenciais e nome do bucket ficam nos secrets do host de deploy, não no repositório. Evitar armazenar arquivos no disco local da aplicação em Stage/produção.

## Módulos do Sistema

Autenticação, Usuários, Perfis, Categorias, Solicitações (inclui cálculo da faixa de preço, `10-motor-precificacao.md`), Propostas, Serviços, Agenda, Chat, Pagamentos, Garantias, Prontuário do Imóvel, Avaliações, Notificações, Administração (inclui `TabelaPreco`).

> "Contratações" removido em 2026-08-17, não existe entidade `Contratação` (INV-020). O que a proposta aceita gera é o módulo `Serviços` diretamente.

Cada módulo deve ser isolado por domínio, mesmo dentro do monólito.

## Autenticação

- Laravel Sanctum (Bearer Token)
- Login por senha, código por e-mail (`POST /auth/magic-link`) e Google OAuth (`/auth/google/*`)
- Logout (invalidação do token)
- Recuperação de senha (fluxo clássico ainda disponível)
- Verificação de e-mail/telefone (evolução)

## Autorização

RBAC (Role-Based Access Control). Papéis: Cliente, Profissional, Administrador. Permissões controladas por Policies/Gates (Laravel).

## Observabilidade

Ferramentas sugeridas: Sentry (erros), Laravel Telescope (desenvolvimento), OpenTelemetry (futuro).

## Logs

Registrar: login, logout, cadastro, aceite de proposta, pagamentos, cancelamentos, alterações cadastrais, disputas. Formato estruturado (JSON).

## Auditoria

Auditar ações críticas: alteração de comissão, exclusão lógica, mudança de status, liberação manual de pagamento, suspensão de usuários.

## Processamento Assíncrono

Executar em filas: envio de e-mails, push notifications, SMS, geração de histórico, atualização de reputação, processamento de imagens.

## Eventos Internos

| Evento | Ação |
|---|---|
| Usuário cadastrado | Enviar boas-vindas |
| Solicitação criada | Notificar profissionais |
| Proposta enviada | Notificar cliente |
| Proposta aceita | Evento `ProposalAccepted` (INV-020) → cria Serviço diretamente, sem entidade `Contratação` |
| Serviço aprovado | Captura de cartão (Pix já capturado no aceite, `adr/ADR-005-gateway-pagamento.md`) + gera garantia + gera `Intervention` no prontuário + recalcula `PerfilProfissional` (P8) |
| Avaliação registrada | Recalcular `PerfilProfissional` (nota média e elegibilidade de nível, P8) |
| Serviço cancelado / disputa resolvida / garantia acionada | Recalcular taxa de cancelamento e/ou reclamações em `PerfilProfissional` (P8) |

## Integrações Externas

**Pagamentos**: Asaas (único gateway no MVP). Cartão: autorizar/capturar com split nativo na captura. Pix: nasce `PENDENTE`, webhook confirma → `CAPTURADO` (INV-047, corrigido em 2026-08-20; não é captura imediata), split de domínio no `CAPTURADO`, dinheiro no `REPASSADO`. Ver `adr/ADR-005-gateway-pagamento.md` (B006). Mercado Pago e Stripe foram avaliados e descartados para a v1.

**Push**: Firebase Cloud Messaging (FCM)

**Mapas**: Google Maps, Mapbox, usados para geolocalização, distância, endereços.

**CEP**: BrasilAPI (`https://brasilapi.com.br/api/cep/v2/{cep}`), para preenchimento automático do endereço.

**IA (Futuro)**: OpenAI ou modelo equivalente para organização automática do escopo, estimativa inicial de preço, sugestão de materiais. Fora do MVP. A faixa de preço do MVP **não** é este item: é a heurística de `TabelaPreco` + `template_escopo` em `10-motor-precificacao.md` (OBJ-MVP-01 / OBJ-TEC-02 / RF029).

## Escalabilidade

**Horizontal**: backend stateless, adicionar instâncias conforme demanda.

**Vertical**: escalar banco e Redis inicialmente.

**Futuro**: separar módulos de maior carga, chat, notificações, pagamentos, IA.

## Segurança

HTTPS obrigatório, senhas com Argon2id, criptografia de dados sensíveis, rate limit, CSRF (Web), CORS configurado, upload validado, antivírus para anexos (futuro).

## Deploy

**Ambiente**: Docker Compose. Containers: app (Nginx+PHP), PostgreSQL, Redis, queue worker, scheduler. Stage sobe com `--env-file` de secrets no host de deploy; Traefik roteia o domínio da Stage.

**CI/CD**: GitHub Actions no runner self-hosted da Stage. Pipeline: Pint, Larastan/PHPStan, PHPUnit com Postgres efêmero (mesma imagem do compose). Deploy da Stage é o compose no host — ver cabeçalho de `compose.yml`.

**Infraestrutura**: host de Stage dimensionado para o MVP (sem HA; Postgres/Redis no próprio compose). Produção futura deve migrar banco/redis para padrão gerenciado. Endereço e hostname do host ficam na config de infra / Vault.

## Monitoramento

Métricas: tempo de resposta, erros por minuto, fila, CPU, memória, banco, tempo médio de contratação, tempo médio de conclusão.

## Backup

**Banco**: diário, retenção de 30 dias.

**Storage**: replicação automática.

## Estratégia de Versionamento

API versionada (`/v1`). Versionamento Semântico (SemVer). Migrations incrementais.

## Organização do Código

```
app/
 ├── Auth
 ├── Users
 ├── Categories
 ├── Requests
 ├── Proposals
 ├── Services
 ├── Payments
 ├── Warranty
 ├── PropertyHistory
 ├── Notifications
 └── Admin
```

Evitar estrutura baseada apenas em Controllers e Models. Organizar por domínio facilita manutenção.

## Decisões Arquiteturais

| Decisão | Justificativa |
|---|---|
| Monólito Modular | Menor custo e maior velocidade no MVP |
| PostgreSQL | Robustez e suporte geográfico |
| Redis | Cache, filas e performance |
| Object Storage | Escalabilidade para imagens |
| Flutter | Código único para Android/iOS |
| Laravel | Alta produtividade e ecossistema maduro |
| REST API | Simplicidade e ampla compatibilidade |
| Asaas (pagamentos) | Split nativo, reautorização off-session, Pix first-class no BR (`adr/ADR-005-gateway-pagamento.md`) |

## Backlog Técnico (Pós-MVP)

- WebSockets para chat em tempo real.
- IA para criação de escopo.
- IA para estimativa de preço.
- Sistema antifraude.
- Busca geoespacial com PostGIS.
- Elastic/OpenSearch para pesquisa.
- Kubernetes.
- Microsserviços.
- Event Bus.
- Multi-região.
- Multi-tenant para empresas.

## Pendências para Validação

> Flutter/Laravel removidos desta lista em 2026-08-17, decididos em ADR-001. Gateway de pagamento removido em 2026-08-17 (`adr/ADR-005-gateway-pagamento.md`, Asaas, B006). Login Google entregue na API/app (código + OAuth); Apple segue Pós-MVP. Chat no MVP é polling (dívida aceitável em `08-planejamento.md`). Painel Admin é aplicação separada (`resolve-plus-admin`, Next.js), consumindo a API deste repositório. Stage roda em host próprio via Docker Compose + Traefik.

- Google Maps ou Mapbox?
- WebSocket para chat (Pós-MVP)?
- Hospedagem de produção: manter host próprio ou cloud gerenciada?

## Avaliação da Arquitetura

Complexidade: Média. A solução prioriza velocidade de desenvolvimento e baixo custo operacional, mas já prepara o sistema para crescimento sem grandes refatorações. A principal recomendação é resistir à tentação de adotar microsserviços no MVP. O volume inicial não justifica a complexidade adicional, e um monólito modular bem estruturado atenderá com folga as primeiras fases do produto.
