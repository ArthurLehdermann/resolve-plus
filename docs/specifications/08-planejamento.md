# 08: Planejamento do MVP

## Objetivo

Transformar a visão do produto em um plano de execução incremental, reduzindo riscos técnicos e de negócio, permitindo validar rapidamente a proposta de valor.

## Roadmap

### Fase 0 • Descoberta (1 a 2 semanas)

**Objetivos**: validar regras de negócio, definir identidade visual, escolher stack definitiva, integrar gateway de pagamento (Asaas, `adr/ADR-005-gateway-pagamento.md`), validar aspectos jurídicos (LGPD, termos de uso e mediação).

**Entregáveis**: documentação funcional, protótipos (Figma), arquitetura, backlog priorizado.

### Fase 1 • MVP (8 a 12 semanas)

**Épico 1, Autenticação**: cadastro, login, recuperação de senha, perfil.

**Épico 2, Solicitações**: criar solicitação, upload de fotos, endereço, categorias.

**Épico 3, Propostas**: recebimento, comparação, aceite, rejeição.

**Épico 4, Execução**: agenda, chat, conclusão, evidências.

**Épico 5, Financeiro**: pagamento protegido, liberação, histórico.

**Épico 6, Avaliações**: avaliação, reputação, garantia, histórico do imóvel.

**Épico 7, Administração**: gestão de usuários, categorias, serviços, relatórios básicos.

### Pós-MVP (V1)

Notificações em tempo real, login Google/Apple, múltiplos imóveis, painel financeiro avançado, cupons, cashback, sistema de disputas, painel de suporte.

### V2

IA para geração automática do escopo, IA para estimativa de preço, lista automática de materiais, integração com lojas de construção, busca geográfica avançada (PostGIS).

### V3

Plataforma para empresas, condomínios, imobiliárias, franquias, contratos recorrentes, API pública.

### Longo Prazo

Marketplace de materiais, seguro para serviços, assinatura de manutenção preventiva, expansão nacional, inteligência de precificação baseada em dados.

## Backlog Inicial

**Prioridade P0 (Obrigatório)**: cadastro e login, cadastro de profissionais, cadastro de clientes, categorias, solicitação de serviço, upload de fotos, propostas, contratação, agenda, chat, conclusão, pagamento protegido, avaliação, garantia, histórico do imóvel, painel administrativo.

**Prioridade P1 (Importante)**: push notifications, recuperação de senha, perfil avançado, indicadores, dashboard, relatórios.

**Prioridade P2 (Evolução)**: IA, materiais, empresas, fidelidade, cupons, cashback.

## User Stories

**US001**, Como cliente, quero solicitar um serviço, para receber propostas de profissionais.
Critérios de Aceite: categoria obrigatória, descrição obrigatória, endereço válido, solicitação criada com sucesso.

**US002**, Como profissional, quero enviar uma proposta, para disputar uma oportunidade.
Critérios: valor obrigatório, prazo obrigatório, garantia informada.

**US003**, Como cliente, quero comparar propostas, para contratar o melhor profissional.

**US004**, Como profissional, quero finalizar um serviço, para receber meu pagamento.

**US005**, Como cliente, quero avaliar o profissional, para contribuir com sua reputação.

## Critérios de Aceite do MVP

O MVP será considerado pronto quando permitir: cadastro e autenticação de clientes e profissionais, solicitação de serviços com fotos, recebimento de propostas, contratação, chat entre as partes, registro da execução, aprovação do cliente, liberação do pagamento, avaliação, geração da garantia, registro no histórico do imóvel, painel administrativo funcional.

## Dependências

**Técnicas**: Asaas (pagamentos, `adr/ADR-005-gateway-pagamento.md`), Firebase Cloud Messaging, serviço de mapas, Object Storage, Redis, PostgreSQL.

**Organizacionais**: definição da marca, política de garantia, política de cancelamento, política de mediação, termos de uso, política de privacidade.

## Estimativa de Complexidade

| Módulo | Complexidade |
|---|---|
| Autenticação | Baixa |
| Usuários | Baixa |
| Categorias | Baixa |
| Solicitações | Média |
| Propostas | Média |
| Agenda | Média |
| Chat | Média |
| Pagamentos | Alta |
| Garantia | Média |
| Histórico do imóvel | Média |
| Administração | Média |
| IA (futuro) | Muito Alta |

## Estimativa de Esforço (1 Dev Full Stack)

| Fase | Semanas |
|---|---|
| Arquitetura e Setup | 1 |
| Backend | 4 |
| Frontend Mobile | 4 |
| Painel Admin | 2 |
| Integrações | 2 |
| Testes e Ajustes | 2 |

Total estimado: 12 a 15 semanas.

Com dois desenvolvedores, o prazo pode cair para aproximadamente 8 a 10 semanas, dependendo da experiência da equipe.

> **Não recalculado desde 2026-08-16.** O escopo do módulo Pagamentos cresceu de uma tabela simples para 5 entidades com reautorização (INV-046), e o módulo Histórico do Imóvel de um log para `Property/Area/Asset/Intervention` com `PropertyOwnership`. Tratar "Alta"/"Média" na tabela de complexidade acima e as 12-15 semanas como desatualizadas até nova estimativa.

## Dívidas Técnicas Aceitáveis no MVP

- Chat via polling em vez de WebSocket.
- Busca geográfica simples em vez de PostGIS.
- Sem IA.
- Sem sistema completo de disputas.
- Sem módulo empresarial.
- Sem múltiplos gateways de pagamento.
- Sem internacionalização.
- Sem modo offline.

## Perguntas em Aberto

**Críticas**
- Quem será o responsável legal pela garantia: plataforma ou profissional? (B001, decisão provisória: profissional, sem retenção; parecer jurídico definitivo ainda bloqueado)
- Como ocorrerá a mediação de conflitos? (B003, em elaboração, ver `foundation/03-cancellation-rules.md`)
- Quais documentos serão obrigatórios para validar um profissional? (RF002, ainda sem critério)
- Quais categorias estarão disponíveis no lançamento?
- Quem responde por dano ao imóvel causado pelo profissional? (B005, novo em 2026-08-17)

> "Pagamento em escrow ou autorizado/capturado depois?" removida desta lista em 2026-08-17, respondida por `adr/ADR-002-financeiro.md` (autorizar→capturar→repassar, não escrow). Pendências residuais (Pix, reautorização, split nativo) resolvidas em 2026-08-17 por `adr/ADR-005-gateway-pagamento.md` (Asaas, Pix aceito com captura imediata, B006).

**Altas**
- Comissão fixa ou variável?
- Prazo padrão da garantia?
- Regras de cancelamento?
- Critérios de reputação?
- Cidade piloto?

**Médias**
- Login social?
- Cupom de desconto?
- Programa de indicação?
- Suporte via WhatsApp?

## Assunções

- O MVP será lançado em uma única cidade.
- A plataforma terá foco em serviços residenciais.
- Haverá oferta suficiente de profissionais.
- O pagamento protegido aumenta a confiança dos usuários.
- O histórico do imóvel será o principal diferencial competitivo.
- A IA não fará parte da primeira versão.

## Decisões Técnicas Consolidadas

- Arquitetura em monólito modular.
- API REST versionada.
- PostgreSQL como banco principal.
- Redis para cache e filas.
- Object Storage para arquivos.
- Flutter para aplicativo móvel.
- Laravel como backend.
- UUID como chave primária.
- Soft Delete em entidades críticas.
- Processamento assíncrono via filas.
- Autenticação por Bearer Token.

## Prontidão para Desenvolvimento

**Avaliação**: 88%, **número obsoleto, não usar.** `foundation/notas-revisao-arquitetural.md` (2026-08-16) já tinha rebaixado para 70-75%; o modelo de dados só foi corrigido de fato em 2026-08-17 (duas rodadas de revisão crítica), e o escopo cresceu no processo (bounded context Payment com 5 entidades + reautorização, `PropertyOwnership`, avaliação bidirecional, mecanismo antidesintermediação). Não há uma reavaliação numérica pós-correção, pendente de nova rodada de Planejamento antes de citar qualquer percentual.

**Pontos Prontos**: visão de produto definida, fluxos principais mapeados, escopo do MVP delimitado, arquitetura definida, APIs especificadas, requisitos não funcionais levantados, roadmap e backlog estruturados.

**Bloqueadores**: parecer jurídico definitivo de garantia (B001, decisão provisória já permite desenvolver), mediação/cancelamento (B003, rascunho em elaboração) e responsabilidade civil por dano ao imóvel (B005), critérios de validação de profissionais (RF002), protótipos de interface em alta fidelidade. Gateway de pagamento deixou de ser bloqueador em 2026-08-17 (`adr/ADR-005-gateway-pagamento.md`, B006).

## Análise Crítica da Conversa

**Informações Ausentes**: modelo operacional do pagamento protegido, responsabilidade legal da plataforma, estratégia de aquisição de usuários, processo de suporte ao cliente, fluxo completo de disputas, regras fiscais (nota fiscal, retenções, impostos), KPIs de negócio esperados.

**Ambiguidades**
- O "Prontuário do Imóvel" é tratado como diferencial, mas não foi definido se permitirá registros manuais ou apenas serviços realizados pela plataforma.
- Não está claro se o cliente poderá contratar múltiplos profissionais para uma mesma solicitação.
- O conceito de "garantia" precisa ser juridicamente detalhado.

## Recomendações

- Validar o modelo jurídico antes de desenvolver o pagamento protegido. Isso impacta arquitetura, fluxo financeiro e responsabilidade da plataforma.
- Criar protótipos navegáveis (Figma) antes da implementação para validar UX e reduzir retrabalho.
- Implementar telemetria desde o primeiro dia (eventos, métricas e funil de conversão) para medir o sucesso do MVP.
- Lançar em uma única cidade, com poucas categorias (elétrica, hidráulica, montagem e pintura), conforme definido na visão do produto.
- Evitar funcionalidades de baixa validação (IA, marketplace de materiais e módulo empresarial) até que o core do marketplace esteja comprovado.

## Conclusão

A documentação produzida fornece uma base sólida para iniciar o desenvolvimento do MVP. O escopo está suficientemente definido para arquitetura, modelagem de dados, APIs e implementação. Os principais riscos remanescentes não são técnicos, mas jurídicos e operacionais, especialmente relacionados ao pagamento protegido, garantias e mediação de conflitos.

Com os bloqueadores resolvidos, o projeto está apto para iniciar a implementação.
