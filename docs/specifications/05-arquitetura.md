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

Object Storage (S3 compatível), para armazenar fotos, documentos, evidências, avatares. Evitar armazenar arquivos no servidor da aplicação.

## Módulos do Sistema

Autenticação, Usuários, Perfis, Categorias, Solicitações (inclui cálculo da faixa de preço, `10-motor-precificacao.md`), Propostas, Serviços, Agenda, Chat, Pagamentos, Garantias, Prontuário do Imóvel, Avaliações, Notificações, Administração (inclui `TabelaPreco`).

> "Contratações" removido em 2026-08-17, não existe entidade `Contratação` (INV-020). O que a proposta aceita gera é o módulo `Serviços` diretamente.

Cada módulo deve ser isolado por domínio, mesmo dentro do monólito.

## Autenticação

- JWT ou Laravel Sanctum
- Refresh Token
- Logout em todos os dispositivos
- Recuperação de senha
- Verificação de e-mail e telefone

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
| Serviço aprovado | Captura de cartão (Pix já capturado no aceite, `adr/ADR-005-gateway-pagamento.md`) + gera garantia + gera `Intervention` no prontuário |
| Avaliação registrada | Atualizar reputação |
| Avaliação criada | Recalcular nota |

## Integrações Externas

**Pagamentos**: Asaas (único gateway no MVP). Cartão: autorizar/capturar com split nativo na captura. Pix: captura imediata, split de domínio no `CAPTURADO`, dinheiro no `REPASSADO`. Ver `adr/ADR-005-gateway-pagamento.md` (B006). Mercado Pago e Stripe foram avaliados e descartados para a v1.

**Push**: Firebase Cloud Messaging (FCM)

**Mapas**: Google Maps, Mapbox, usados para geolocalização, distância, endereços.

**CEP**: ViaCEP (Brasil), para preenchimento automático do endereço.

**IA (Futuro)**: OpenAI ou modelo equivalente para organização automática do escopo, estimativa inicial de preço, sugestão de materiais. Fora do MVP. A faixa de preço do MVP **não** é este item: é a heurística de `TabelaPreco` + `template_escopo` em `10-motor-precificacao.md` (OBJ-MVP-01 / OBJ-TEC-02 / RF029).

## Escalabilidade

**Horizontal**: backend stateless, adicionar instâncias conforme demanda.

**Vertical**: escalar banco e Redis inicialmente.

**Futuro**: separar módulos de maior carga, chat, notificações, pagamentos, IA.

## Segurança

HTTPS obrigatório, senhas com Argon2id, criptografia de dados sensíveis, rate limit, CSRF (Web), CORS configurado, upload validado, antivírus para anexos (futuro).

## Deploy

**Ambiente**: Docker. Containers: Backend, Nginx, PostgreSQL, Redis, Queue Worker.

**CI/CD**: GitHub Actions. Pipeline: testes, análise estática, build, deploy.

**Infraestrutura**: ambiente inicial, VPS 4 vCPU, 8 GB RAM, SSD NVMe, Ubuntu LTS. Escalável para AWS, Azure ou DigitalOcean posteriormente.

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

> Flutter/Laravel removidos desta lista em 2026-08-17, decididos em ADR-001, listá-los aqui como pendência contradizia o próprio ADR. Gateway de pagamento removido em 2026-08-17, decidido em `adr/ADR-005-gateway-pagamento.md` (Asaas, B006).

- Google Maps ou Mapbox?
- Chat via polling no MVP ou WebSocket?
- Login social (Google/Apple) será incluído?
- Hospedagem inicial (VPS própria ou cloud gerenciada)?
- O painel administrativo será integrado ao backend ou uma aplicação separada?

## Avaliação da Arquitetura

Complexidade: Média. A solução prioriza velocidade de desenvolvimento e baixo custo operacional, mas já prepara o sistema para crescimento sem grandes refatorações. A principal recomendação é resistir à tentação de adotar microsserviços no MVP. O volume inicial não justifica a complexidade adicional, e um monólito modular bem estruturado atenderá com folga as primeiras fases do produto.
