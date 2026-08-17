# 07 — Requisitos Não Funcionais

## Visão Geral

Os requisitos não funcionais garantem que o sistema seja seguro, escalável, performático e preparado para crescimento, sem aumentar desnecessariamente o custo do MVP.

## RNF001 • Performance

**Objetivos**
- Tempo médio de resposta da API < 500 ms (95% das requisições)
- Tempo máximo para operações críticas < 2 s
- Upload de imagens com processamento assíncrono
- Paginação obrigatória em listas

**Estratégias**: índices no banco, cache de consultas frequentes, lazy loading, compressão GZIP/Brotli, CDN para arquivos estáticos.

## RNF002 • Escalabilidade

- Suportar crescimento horizontal do backend
- Aplicação stateless
- Banco preparado para replicação de leitura
- Separação futura de módulos (Chat, IA, Pagamentos)

## RNF003 • Segurança

HTTPS obrigatório, senhas com Argon2id, JWT/Sanctum com expiração, criptografia de dados sensíveis, proteção contra SQL Injection, proteção contra XSS, proteção contra CSRF (Web), CORS configurado, Rate Limit, upload validado por extensão/MIME/tamanho.

## RNF004 • LGPD

**Dados pessoais**: nome, CPF/CNPJ, e-mail, telefone, endereço, geolocalização, fotos.

**Requisitos**: consentimento para tratamento de dados, política de privacidade, exclusão lógica da conta, exportação dos dados do usuário, registro de consentimento, controle de retenção de dados.

**Necessita Validação**: prazos legais e política de descarte.

## RNF005 • Backup

**Banco**: backup diário, retenção de 30 dias, teste periódico de restauração.

**Arquivos**: replicação em Object Storage, versionamento de objetos (quando suportado).

## RNF006 • Monitoramento

Monitorar: tempo de resposta, erros HTTP, CPU, memória, espaço em disco, conexões ao banco, filas, jobs falhos, uso da API.

Ferramentas sugeridas: Grafana, Prometheus, Sentry.

## RNF007 • Logs

Registrar: login, logout, alterações cadastrais, solicitações, propostas, contratações, pagamentos, cancelamentos, erros.

Requisitos: estruturados em JSON, correlação por Request ID, níveis (INFO, WARN, ERROR).

## RNF008 • Auditoria

Auditar ações críticas: alteração de usuários, mudança de permissões, liberação manual de pagamentos, exclusões, suspensões, configurações do sistema.

## RNF009 • Disponibilidade

Meta inicial: SLA de 99,5%.

Estratégias: health check, restart automático, deploy sem downtime (quando possível).

## RNF010 • Concorrência

Evitar contratação simultânea da mesma proposta, controle transacional na aceitação de propostas, locks otimistas/pessimistas conforme necessidade, idempotência em operações críticas (pagamentos e aprovações).

## RNF011 • Cache

Utilizar Redis para: sessões, categorias, configurações, rate limit, resultados de consultas frequentes. Invalidar cache após alterações relevantes.

## RNF012 • Rate Limit

Sugestão inicial:

| Recurso | Limite |
|---|---|
| Login | 5/minuto |
| Cadastro | 10/hora/IP |
| API autenticada | 120/minuto |
| Upload | 30/hora |
| Chat | 60/minuto |

## RNF013 • Acessibilidade

Atender, quando possível: WCAG 2.1 AA, navegação por teclado (Web), contraste adequado, textos alternativos para imagens, fontes escaláveis.

## RNF014 • Internacionalização

MVP: português (pt-BR). Preparar arquitetura para múltiplos idiomas sem refatoração.

## RNF015 • Compatibilidade

**Aplicativo**: Android 10+, iOS 16+

**Web**: Chrome, Edge, Safari, Firefox — últimas duas versões estáveis.

## RNF016 • Custos Operacionais

Objetivos do MVP: infraestrutura < R$ 500/mês (estimativa inicial), utilizar serviços gerenciados apenas quando agregarem valor, evitar dependências de alto custo antes da validação.

## RNF017 • Testes

Cobertura recomendada: unitários ≥ 70%, integração para fluxos críticos, testes E2E para jornada principal, testes de carga básicos antes da produção.

## RNF018 • Observabilidade

Implementar: Request ID, Trace ID (futuro), métricas por endpoint, dashboard operacional, alertas automáticos para falhas críticas.

## RNF019 • Armazenamento de Arquivos

Object Storage (S3 compatível), limite de tamanho por arquivo (**Necessita Validação**), compressão automática de imagens, geração de miniaturas para listagens.

## RNF020 • Notificações

Canais: push (FCM), e-mail, SMS (futuro). Envio assíncrono via filas.

## Métricas Técnicas

| Métrica | Meta |
|---|---|
| Tempo médio da API | < 500 ms |
| Disponibilidade | ≥ 99,5% |
| Erros 5xx | < 1% |
| Jobs falhos | < 0,5% |
| Uso de CPU | < 70% |
| Uso de Memória | < 80% |
| Tempo médio de deploy | < 10 min |

## Riscos Técnicos

| Risco | Impacto | Mitigação |
|---|---|---|
| Baixa adesão de profissionais | Alto | Lançar em uma cidade com oferta suficiente |
| Falhas no gateway de pagamento | Alto | Abstração por interface e suporte a múltiplos gateways |
| Crescimento do volume de imagens | Médio | Object Storage + CDN |
| Geolocalização imprecisa | Médio | Validar endereço + coordenadas |
| Escalabilidade do chat | Médio | Migrar para WebSocket após MVP |
| Custos de infraestrutura | Médio | Arquitetura enxuta e monitoramento |

## Pendências para Validação

- SLA contratado para produção.
- Tempo máximo aceitável para processamento de uploads.
- Política de retenção de logs.
- Estratégia de Disaster Recovery.
- Política de criptografia de backups.
- Limites de armazenamento por usuário.
- Necessidade de certificações (ISO 27001, PCI DSS, etc.).
- Exigência de pentest antes do lançamento.

## Avaliação

O conjunto de requisitos não funcionais é suficiente para um MVP com potencial de crescimento. A recomendação é não superdimensionar a infraestrutura antes da validação do mercado, mas implementar desde o início boas práticas de segurança, observabilidade e testes, pois são muito mais baratos de adotar no começo do que corrigir posteriormente.
