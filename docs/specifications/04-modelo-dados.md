# 04 — Modelo de Dados do MVP

## Visão Geral

O domínio é centrado em uma **Solicitação de Serviço**, que evolui para uma **Contratação**, gera um **Serviço**, um **Pagamento**, uma **Garantia** e passa a compor o **Histórico do Imóvel**.

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
Solicitação
        │
        ├── Fotos
        ├── Propostas
        └── Endereço
               │
               ▼
Contratação
       │
       ▼
Serviço
 ├── Agenda
 ├── Chat
 ├── Pagamento
 ├── Garantia
 ├── Avaliação
 └── Histórico do Imóvel
```

## Entidades

### Usuario

**Finalidade**: representa qualquer pessoa autenticada.

| Campo | Tipo | Obrigatório | Índice |
|---|---|---|---|
| id | UUID | Sim | PK |
| tipo | ENUM | Sim | Sim |
| nome | VARCHAR(150) | Sim | |
| email | VARCHAR(150) | Sim | Unique |
| telefone | VARCHAR(20) | Sim | |
| senha_hash | VARCHAR | Sim | |
| foto | VARCHAR | Não | |
| status | ENUM | Sim | Sim |
| criado_em | TIMESTAMP | Sim | |

**Relacionamentos**: 1:N Solicitações, 1:N Propostas, 1:N Serviços, 1:N Avaliações

### Endereco

**Campos**: id, usuario_id, cep, logradouro, número, complemento, bairro, cidade, estado, latitude, longitude

**Relacionamento**: Usuário 1:N Endereço

### Categoria

**Campos**: id, nome, descrição, ativo

**Exemplos**: Elétrica, Hidráulica, Pintura, Jardinagem

### Solicitacao

Representa o pedido inicial.

**Campos**: id, cliente_id, categoria_id, endereço_id, descrição, status, data_desejada, criado_em

**Relacionamentos**: Cliente, Categoria, Fotos, Propostas

### FotoSolicitacao

**Campos**: id, solicitacao_id, url, ordem

### Proposta

**Campos**: id, solicitacao_id, profissional_id, valor, prazo, garantia_dias, observações, status

### Contratacao

Representa a proposta aceita.

**Campos**: id, proposta_id, data, status

### Servico

**Campos**: id, contratação_id, início, fim, status

### Agenda

**Campos**: id, serviço_id, data, hora, observações

### Mensagem

**Campos**: id, serviço_id, remetente_id, texto, anexo, enviado_em

### Pagamento

**Campos**: id, serviço_id, valor, status, método, criado_em, liberado_em

### Garantia

**Campos**: id, serviço_id, início, fim, status

### Avaliação

**Campos**: id, serviço_id, cliente_id, profissional_id, nota, comentário

**Validação**: nota entre 1 e 5.

### Imóvel

*Inferência* — embora inicialmente possa ser tratado apenas pelo endereço, recomenda-se criar uma entidade própria desde o MVP.

**Campos**: id, cliente_id, endereço_id, apelido

**Relacionamentos**: 1:N Histórico

### HistoricoImovel

**Campos**: id, imóvel_id, serviço_id, data, categoria, resumo

## Relacionamentos

| Origem | Destino | Cardinalidade |
|---|---|---|
| Usuário | Endereço | 1:N |
| Usuário | Solicitação | 1:N |
| Categoria | Solicitação | 1:N |
| Solicitação | Foto | 1:N |
| Solicitação | Proposta | 1:N |
| Proposta | Contratação | 1:1 |
| Contratação | Serviço | 1:1 |
| Serviço | Pagamento | 1:1 |
| Serviço | Garantia | 1:1 |
| Serviço | Avaliação | 1:1 |
| Imóvel | Histórico | 1:N |

## Enumerações

**TipoUsuario**: CLIENTE, PROFISSIONAL, ADMIN

**StatusConta**: PENDENTE, ATIVA, SUSPENSA, BLOQUEADA

**StatusSolicitacao**: CRIADA, ABERTA, RECEBENDO_PROPOSTAS, CONTRATADA, CANCELADA, EXPIRADA

**StatusProposta**: ENVIADA, ACEITA, RECUSADA, CANCELADA

**StatusServico**: AGENDADO, EM_ANDAMENTO, CONCLUIDO, CONTESTACAO, FINALIZADO

**StatusPagamento**: PENDENTE, RETIDO, LIBERADO, PAGO, REEMBOLSADO

**StatusGarantia**: ATIVA, EXPIRADA, ACIONADA

## Índices Recomendados

**Usuário**: email (Unique), telefone, tipo

**Solicitação**: cliente_id, categoria_id, status, criado_em

**Proposta**: profissional_id, solicitacao_id, status

**Serviço**: status, profissional_id, cliente_id

**Pagamento**: status, criado_em

## Regras de Integridade

- Toda solicitação pertence a um cliente.
- Toda proposta pertence a uma solicitação.
- Apenas uma proposta pode ser aceita.
- Toda contratação gera um serviço.
- Todo serviço possui exatamente um pagamento.
- Todo serviço concluído gera uma garantia.
- Toda avaliação pertence a um serviço concluído.
- Todo histórico referencia um serviço existente.

## Tabelas Auxiliares

### Categoria
Lista administrável.

### Configuração
Parâmetros globais: comissão (%), prazo de garantia padrão, tempo limite para aceite, raio máximo de atendimento.

### Notificação
**Campos**: usuário, título, mensagem, lida, data

### DocumentoProfissional
Para futura validação documental.

**Campos**: tipo, arquivo, status

### Auditoria
**Campos**: usuário, ação, entidade, id_entidade, data, IP

## Observações Arquiteturais

- Utilizar UUID como chave primária.
- Soft Delete para entidades críticas (Usuário, Serviço, Pagamento).
- Campos `created_at` e `updated_at` em todas as tabelas.
- Suporte futuro a múltiplos imóveis por cliente.
- Preparar estrutura para expansão ao módulo corporativo sem refatoração significativa.

## Pendências para Validação

- Um cliente poderá cadastrar vários imóveis no MVP ou apenas um endereço?
- O chat será persistido indefinidamente?
- Fotos serão armazenadas localmente ou em object storage?
- Será permitido múltiplos pagamentos por serviço (parcelamento)?
- Como serão tratadas revisitas durante a garantia?
- Haverá emissão de nota fiscal pela plataforma?
- A contratação poderá envolver mais de um profissional?
- O histórico do imóvel armazenará apenas serviços contratados pela plataforma ou permitirá registros manuais?
