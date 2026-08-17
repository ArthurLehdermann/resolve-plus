# 06 — Especificação da API REST (MVP)

## Padrões

- RESTful
- JSON
- UTF-8
- Versionamento: `/api/v1`
- Autenticação: Bearer Token (Sanctum/JWT)
- Datas: ISO-8601 (UTC)
- UUID como identificador

## Convenções

**Sucesso**
```json
{
  "success": true,
  "data": {}
}
```

**Erro**
```json
{
  "success": false,
  "message": "Descrição do erro",
  "errors": {}
}
```

## Autenticação

**POST /auth/register** — Cadastro de cliente ou profissional.

Request
```json
{
  "tipo": "CLIENTE",
  "nome": "",
  "email": "",
  "telefone": "",
  "senha": ""
}
```

Response
```json
{
  "user": {},
  "token": ""
}
```

Erros: 400, 409, 422

**POST /auth/login** — Retorna token de acesso.

**POST /auth/logout** — Invalida token.

**POST /auth/forgot-password** — Solicita redefinição.

**POST /auth/reset-password** — Redefine senha.

## Usuários

**GET /users/me** — Retorna usuário autenticado.

**PUT /users/me** — Atualiza perfil.

**POST /users/photo** — Upload de avatar.

## Categorias

**GET /categories** — Lista categorias disponíveis.

## Imóveis

**GET /properties** — Lista imóveis do cliente.

**POST /properties** — Cadastrar imóvel.

**PUT /properties/{id}** — Editar imóvel.

**DELETE /properties/{id}** — Excluir (soft delete).

## Solicitações

**GET /requests** — Lista solicitações do usuário.

Filtros: `status`, `categoria`, `data`

**POST /requests** — Criar solicitação.

Request
```json
{
  "property_id": "",
  "category_id": "",
  "description": "",
  "desired_date": ""
}
```

**GET /requests/{id}** — Detalhes.

**PUT /requests/{id}** — Editar enquanto aberta.

**DELETE /requests/{id}** — Cancelar.

**POST /requests/{id}/photos** — Upload de imagens.

## Propostas

**GET /requests/{id}/proposals** — Lista propostas.

**POST /requests/{id}/proposals** — Profissional envia proposta.
```json
{
  "price": 350,
  "deadline_days": 2,
  "warranty_days": 90,
  "notes": ""
}
```

**POST /proposals/{id}/accept** — Aceita proposta.

**POST /proposals/{id}/reject** — Recusa proposta.

## Serviços

**GET /services** — Lista serviços.

**GET /services/{id}** — Detalhes.

**POST /services/{id}/start** — Marca início.

**POST /services/{id}/finish** — Finaliza.
```json
{
  "notes": "",
  "photos": []
}
```

**POST /services/{id}/approve** — Cliente aprova.

**POST /services/{id}/contest** — Cliente contesta.

## Chat

**GET /services/{id}/messages** — Lista mensagens. Paginação obrigatória.

**POST /services/{id}/messages** — Enviar mensagem.

## Agenda

**GET /schedule** — Agenda do usuário.

**POST /schedule** — Agendar.

**PUT /schedule/{id}** — Reagendar.

## Pagamentos

**GET /payments** — Histórico.

**GET /payments/{id}** — Detalhes.

**POST /payments/{id}/release** — Liberação manual (Admin), gera `PaymentEvent` fora do fluxo automático (INV-041 exige justificativa e responsável registrados em auditoria).

Request
```json
{
  "justificativa": "",
  "responsavel_id": ""
}
```

Erros: 403 (fora do papel Admin), 409 (serviço não `APROVADO` e sem exceção administrativa), 422 (justificativa ausente)

## Garantias

**GET /warranties** — Lista garantias.

**GET /warranties/{id}** — Detalhes.

## Histórico

**GET /properties/{id}/history** — Histórico do imóvel.

## Avaliações

**POST /services/{id}/rating** — Enviar avaliação.
```json
{
  "score": 5,
  "comment": ""
}
```

## Notificações

**GET /notifications** — Lista.

**PUT /notifications/{id}/read** — Marca como lida.

## Administração

**GET /admin/users** — Usuários.

**GET /admin/services** — Serviços.

**GET /admin/payments** — Pagamentos.

**GET /admin/dashboard** — Indicadores gerais.

## Paginação

Formato padrão: `GET /requests?page=1&per_page=20`

Resposta:
```json
{
  "data": [],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 135,
    "last_page": 7
  }
}
```

## Filtros

Padrão: `?status=`, `?category=`, `?city=`, `?professional=`, `?date_from=`, `?date_to=`

## Ordenação

`sort=created_at`, `sort=-created_at`, `sort=price`

## Códigos HTTP

| Código | Uso |
|---|---|
| 200 | OK |
| 201 | Criado |
| 204 | Sem conteúdo |
| 400 | Requisição inválida |
| 401 | Não autenticado |
| 403 | Sem permissão |
| 404 | Não encontrado |
| 409 | Conflito |
| 422 | Erro de validação |
| 429 | Rate limit |
| 500 | Erro interno |

## Eventos Internos

| Evento | Disparado por |
|---|---|
| UserRegistered | Cadastro |
| RequestCreated | Nova solicitação |
| ProposalCreated | Nova proposta |
| ProposalAccepted | Aceite |
| ServiceStarted | Início |
| ServiceFinished | Conclusão |
| PaymentReleased | Pagamento |
| RatingCreated | Avaliação |

## Filas

Executar em background: envio de e-mail, push notification, SMS, upload/processamento de imagens, cálculo de reputação, geração do histórico do imóvel, integração com gateway de pagamento.

## Webhooks

**Recebidos**: Gateway de pagamento. Eventos esperados: pagamento aprovado, pagamento recusado, estorno, chargeback.

**Futuros**: ERP, CRM, Marketplace de materiais.

## Contratos da API

**Headers**
```
Authorization: Bearer TOKEN
Content-Type: application/json
Accept: application/json
```

**Convenções**: UUID em todas as entidades, soft delete, datas ISO-8601, valores monetários em centavos (inteiro) para evitar erros de ponto flutuante, respostas padronizadas.

## Versionamento

`/api/v1`, `/api/v2`. Nunca quebrar compatibilidade da versão anterior.

## Pendências para Validação

- REST puro ou GraphQL complementar?
- Chat via REST (polling) no MVP ou WebSocket?
- Upload direto para S3 (pré-signed URL) ou via backend?
- Estratégia de refresh token.
- Rate limit por IP, usuário ou ambos?
- API pública para parceiros?
- Documentação OpenAPI/Swagger será obrigatória?

## Avaliação

A API proposta cobre todos os fluxos do MVP e segue convenções REST amplamente adotadas. O único ajuste recomendado antes da implementação é padronizar nomes de recursos em inglês (como `requests`, `services`, `payments`) ou em português, mas nunca misturar idiomas. Também recomenda-se gerar automaticamente a documentação via OpenAPI/Swagger desde o início, garantindo sincronização entre código e documentação.
