# 06: Especificação da API REST (MVP)

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

**POST /auth/register**, Cadastro de cliente ou profissional.

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

**POST /auth/login**, Retorna token de acesso.

**POST /auth/logout**, Invalida token.

**POST /auth/forgot-password**, Solicita redefinição.

**POST /auth/reset-password**, Redefine senha.

## Usuários

**GET /users/me**, Retorna usuário autenticado. Se `tipo = PROFISSIONAL`, inclui objeto `trust_profile` (`nivel_confianca`, `servicos_aprovados`, `nota_media`, `taxa_cancelamento_pct`, `reclamacoes_12m`).

**GET /users/{id}**, Perfil público. Para profissionais, inclui `trust_profile` com badge de nível e nota média (RN026).

**PUT /users/me**, Atualiza perfil.

**POST /users/photo**, Upload de avatar.

## Categorias

**GET /categories**, Lista categorias disponíveis.

**GET /categories/{id}**, Detalhes, inclui `template_escopo` (schema dos campos estruturados que `POST /requests` exige para essa categoria, INV-080). App usa isso pra renderizar o formulário de escopo antes de criar a solicitação. Os 5 templates do MVP (elétrica, hidráulica, pintura, pequenos reparos, montagem) estão na seção Categoria de `04-modelo-dados.md`. Campos do template podem trazer `ajuste_preco` (consumido pelo motor de `10-motor-precificacao.md`, não é campo de formulário).

## Imóveis

> Corrigido em 2026-08-17 (3ª revisão do PO): `GET /properties` descrevia "imóveis do cliente" sem passar por `PropertyOwnership`, exatamente o ownership implícito que INV-014 resolveu para Solicitação, esquecido aqui. `DELETE /properties/{id}` foi removido: um `Property` sobrevive à troca de dono por premissa (INV-063/064) e carrega prontuário, apagá-lo apaga histórico que INV-071 proíbe apagar. A ação real do cliente que "não quer mais" um imóvel é transferir a posse, não excluir.

**GET /properties**, Lista `Property` cujo dono vigente (`PropertyOwnership.ate IS NULL`, `cliente_id` = usuário autenticado) é o cliente autenticado (INV-014).

**POST /properties**, Cadastra `Property` (checa `UNIQUE (chave_endereco)`, INV-063, se já existir, retorna 409 com o `property_id` existente em vez de duplicar) e cria o primeiro `PropertyOwnership` (`cliente_id` = usuário autenticado, `ate = NULL`).

**PUT /properties/{id}**, Editar campos do imóvel (apelido, dados de endereço se houver erro de digitação, não deve permitir editar `chave_endereco` livremente sem revalidar unicidade). Requer ser o dono vigente.

**POST /properties/{id}/transfer**, Dono vigente inicia transferência de posse (INV-064). Cria `PropertyOwnershipTransfer` `PENDENTE`.

Request
```json
{
  "para_cliente_id": "",
  "para_email": ""
}
```

Erros: 403 (não é o dono vigente), 404, 422 (nem `para_cliente_id` nem `para_email` informado)

**POST /property-transfers/{id}/accept**, Novo dono aceita. Fecha o `PropertyOwnership` do dono anterior e abre um novo (mesma transação). Requer ser o `para_cliente_id`/dono da conta associada a `para_email`.

**POST /property-transfers/{id}/decline**, Novo dono recusa. `PropertyOwnershipTransfer.status = RECUSADO`, `PropertyOwnership` não muda.

**GET /property-transfers**, Lista transferências pendentes do usuário autenticado (como origem ou destino).

## Solicitações

> Adicionado em 2026-08-17 (issue #2): `POST /requests/estimate` e snapshot `estimated_price_*` na criação/detalhe. Heurística em `10-motor-precificacao.md`. Declarar a rota `/requests/estimate` **antes** de `/requests/{id}` para `estimate` não ser lido como UUID.

**GET /requests**, Lista solicitações do usuário.

Filtros: `status`, `categoria`, `data`

**POST /requests/estimate**, Pré-visualização da faixa (OBJ-MVP-01: antes de contratar / antes de falar com profissional). Mesmo body de `POST /requests`, **não persiste**. Recalcula a cada chamada.

Request
```json
{
  "property_id": "",
  "category_id": "",
  "description": "",
  "scope": {},
  "desired_date": ""
}
```

Response `200`
```json
{
  "estimated_price_min": 8000,
  "estimated_price_max": 25000,
  "estimated_price_factor_bp": 10000
}
```

Valores em centavos. `estimated_price_min < estimated_price_max` sempre.

Erros: 422 (`scope` não bate com `template_escopo` da categoria; código `PRECO_TABELA_AUSENTE` se não houver `TabelaPreco` ativa para a categoria + `Property.cidade`)

**POST /requests**, Criar solicitação. Valida `scope` contra `Categoria.template_escopo` da `category_id` informada (INV-080), campo obrigatório do template ausente é 422. Calcula a faixa pela heurística de `10-motor-precificacao.md`, grava snapshot (`faixa_preco_min`/`faixa_preco_max`/`faixa_preco_fator_bp`/`tabela_preco_id`) e devolve a faixa na resposta. Ignora qualquer `estimated_price_*` enviado no body (a faixa não é input do cliente).

Request
```json
{
  "property_id": "",
  "category_id": "",
  "description": "",
  "scope": {},
  "desired_date": ""
}
```

Response `201`
```json
{
  "id": "",
  "status": "CRIADA",
  "estimated_price_min": 8000,
  "estimated_price_max": 25000,
  "estimated_price_factor_bp": 10000,
  "price_table_id": ""
}
```

Erros: 422 (`scope` não bate com `template_escopo` da categoria; código `PRECO_TABELA_AUSENTE` se não houver `TabelaPreco` ativa para a categoria + `Property.cidade`)

**GET /requests/{id}**, Detalhes. Inclui o snapshot `estimated_price_min`, `estimated_price_max`, `estimated_price_factor_bp`, `price_table_id`.

**PUT /requests/{id}**, Editar enquanto aberta. `scope` não é editável se já existir proposta para a solicitação (mudar escopo depois de propostas enviadas quebra a comparabilidade que gerou, INV-080), 409 nesse caso. Se `scope`/`category_id`/`property_id` mudam e ainda não há proposta, recalcula e persiste o snapshot da faixa. Se já houver proposta, o snapshot permanece congelado com o escopo.

**DELETE /requests/{id}**, Cancelar.

**POST /requests/{id}/photos**, Upload de imagens.

## Propostas

**GET /requests/{id}/proposals**, Lista propostas. Cada item inclui `professional.trust_level` e `professional.average_rating` (RN026).

**POST /requests/{id}/proposals**, Profissional envia proposta. No MVP, `price` **não** é validado contra a faixa estimada da solicitação (`10-motor-precificacao.md` §4).
```json
{
  "price": 350,
  "deadline_days": 2,
  "warranty_days": 90,
  "notes": ""
}
```

**POST /proposals/{id}/accept**, Aceita proposta. Recusa automática de todas as demais propostas da mesma solicitação é efeito colateral do sistema, não uma chamada separada (INV-011).

**POST /proposals/{id}/withdraw**, Profissional retira a proposta antes do aceite (`Retirada`, `02-state-machine.md` §2).

> `POST /proposals/{id}/reject` removido em 2026-08-17, não existe recusa manual de proposta individual pelo cliente na state machine. Recusa é sempre automática (efeito colateral do aceite de outra proposta, INV-011). Se Produto quiser um "descartar" puramente de UI (sem mudar `status`), isso é estado local do app, não uma mutação de API.

## Serviços

**GET /services**, Lista serviços.

**GET /services/{id}**, Detalhes.

**POST /services/{id}/start**, Marca início (`Agendado → Em Andamento`). Só o profissional da proposta aceita. Bloqueia com 409 se a `PaymentAuthorization` do serviço estiver `PENDENTE` (Pix ainda não confirmado pelo webhook) ou ausente (INV-048, adicionado em 2026-08-20); `CAPTURADO`/`AUTORIZADO` liberam.

**POST /services/{id}/finish**, Finaliza.
```json
{
  "notes": "",
  "photos": []
}
```

**POST /services/{id}/approve**, Cliente aprova. Dispara captura de pagamento + garantia + Intervention no prontuário (INV-041/050/060). Requer header `Idempotency-Key` (RNF010, operação crítica, nunca duplicar captura).

**POST /services/{id}/contest**, Cliente contesta conclusão (`Aguardando Aprovação` → `Em Contestação`, FA004). Cria `PaymentDispute` com `tipo = CONTESTACAO_CONCLUSAO`. Pausa timer de aceite automático (`AUTO_APPROVAL_HOURS`). Requer `Idempotency-Key` (RNF010).

Request
```json
{
  "motivo": ""
}
```

**POST /services/{id}/cancel**, Cancela serviço. Só válido em `Agendado` (→ `Cancelado`, Cenário B, `foundation/03-cancellation-rules.md`); em `Em Andamento` o mesmo endpoint abre disputa (→ `Em Contestação`, Cenário C, `PaymentDispute.tipo = CANCELAMENTO_EXECUCAO`) em vez de cancelar. Multa Cenário B: percentual decrescente por antecedência (10/25/50%, parâmetros `CANCELLATION_PENALTY_*`), captura parcial da autorização vigente. Requer `Idempotency-Key` (RNF010).

Request (Cenário C, opcional)
```json
{
  "motivo": ""
}
```

Response (Cenário B, exemplo)
```json
{
  "servico": { "status": "CANCELADO" },
  "multa": {
    "percentual": 25,
    "valor_centavos": 8750
  }
}
```

Erros: 403 (não é o cliente, ou estado inválido), 409 (já cancelado/disputa aberta), 422

## Chat

**GET /services/{id}/messages**, Lista mensagens. Paginação obrigatória.

**POST /services/{id}/messages**, Enviar mensagem.

## Agenda

**GET /schedule**, Agenda do usuário.

**POST /schedule**, Agendar.

**PUT /schedule/{id}**, Reagendar.

## Pagamentos

**GET /payments**, Histórico (lista `PaymentAuthorization`, uma ou mais por serviço, INV-046).

**GET /payments/{id}**, Detalhes de uma `PaymentAuthorization`.

**GET /payments/{id}/events**, Extrato de `PaymentEvent` (append-only) daquela autorização, inclui `REAUTORIZADO` quando houver.

**POST /payments/{id}/release**, Liberação manual (Admin), gera `PaymentEvent` fora do fluxo automático (INV-041 exige justificativa e responsável registrados em auditoria). Requer `Idempotency-Key` (RNF010).

Request
```json
{
  "justificativa": "",
  "responsavel_id": ""
}
```

Erros: 403 (fora do papel Admin), 409 (serviço não `APROVADO` e sem exceção administrativa), 422 (justificativa ausente)

**POST /webhooks/asaas**, Recebe eventos de pagamento do Asaas (adicionado em 2026-08-20). Não é uma API de cliente: autenticação é o token de webhook do Asaas, não `sanctum`; roteado por `throttle:webhook-asaas`, não pelos limites padrão de usuário. Todo evento é gravado em `PaymentWebhookEvent` (`04-modelo-dados.md`) antes de qualquer efeito colateral, idempotente por `gateway_event_id` UNIQUE, reentrega do mesmo evento (comportamento normal de webhook) responde 2xx sem reprocessar. Efeitos por tipo de pagamento: Pix `PENDENTE` confirmado (`CONFIRMED`/`RECEIVED`) → `CAPTURADO` (INV-047); cartão com evento de chargeback (`PAYMENT_CHARGEBACK_REQUESTED`/`_DISPUTE`/`PAYMENT_AWAITING_CHARGEBACK_REVERSAL`) → abre `PaymentDispute` `tipo = CHARGEBACK`. Confirmação de um Pix que o sistema já tinha marcado `EXPIRADO`/`CANCELADO` (corrida com `ExpirePendingPixPayments`) reconstrói `CAPTURADO` e registra reembolso pendente em vez de descartar o evento (INV-047). Não requer `Idempotency-Key` (a idempotência é do provedor, via `gateway_event_id`).

## Garantias

**GET /warranties**, Lista garantias.

**GET /warranties/{id}**, Detalhes.

**POST /warranties/{id}/claim**, Cliente aciona garantia com evidências (INV-052).

Request
```json
{
  "descricao": "",
  "photos": []
}
```

## Disputas

**POST /services/{id}/disputes**, Abre disputa sobre o serviço (`PaymentDispute`, INV-045). No MVP, disputas nascem preferencialmente via `POST /services/{id}/cancel` (Cenário C) ou `POST /services/{id}/contest` (contestação de conclusão); este endpoint fica para casos administrativos ou extensões futuras. Bloqueia repasse até resolução, não bloqueia novos `PaymentEvent`. Whitelist de `tipo` aceito neste endpoint **não** inclui `CHARGEBACK` (adicionado em 2026-08-20): esse tipo só é aberto pelo webhook do Asaas (`POST /webhooks/asaas`, `## Pagamentos`), nunca por request de usuário.

**PUT /disputes/{id}/resolve**, Admin resolve disputa (`Usuario.tipo = ADMIN`). Serviço deve estar `Em Contestação`. Critérios em `foundation/03-cancellation-rules.md` (B003): prazo `DISPUTE_MEDIATION_DAYS` (7 dias), timeout automático se Admin não decidir.

Request
```json
{
  "resultado": "APROVADO",
  "justificativa": ""
}
```

`resultado`: `APROVADO` | `CANCELADO`. Efeito depende de `PaymentDispute.tipo`:

| tipo | `APROVADO` | `CANCELADO` |
|---|---|---|
| `CONTESTACAO_CONCLUSAO` | Serviço → `Aprovado`, captura integral | Serviço → `Cancelado`, libera autorização |
| `CANCELAMENTO_EXECUCAO` | Serviço → `Em Andamento` (cancelamento negado) | Serviço → `Cancelado`, libera autorização |

Erros: 403 (não Admin), 404, 409 (disputa já `RESOLVIDA` ou serviço fora de `Em Contestação`), 422 (`justificativa` ausente ou `resultado` inválido)

## Histórico

**GET /properties/{id}/history**, Prontuário do imóvel: `Area → Asset → Intervention` aninhado (não são recursos CRUD próprios no MVP, gerados automaticamente pelo fluxo de Serviço, INV-060/061).

## Avaliações

**POST /services/{id}/rating**, Enviar avaliação.
```json
{
  "score": 5,
  "comment": ""
}
```

## Notificações

**GET /notifications**, Lista.

**PUT /notifications/{id}/read**, Marca como lida.

## Administração

**GET /admin/users**, Usuários.

**GET /admin/services**, Serviços.

**GET /admin/payments**, Pagamentos.

**GET /admin/dashboard**, Indicadores gerais.

**GET /admin/price-tables**, Lista `TabelaPreco` (Admin).

**POST /admin/price-tables**, Cria linha (Admin). Uma linha ativa por `(category_id, city)`.

Request
```json
{
  "category_id": "",
  "city": "",
  "min_amount": 8000,
  "max_amount": 25000,
  "active": true
}
```

`min_amount`/`max_amount` em centavos. Erros: 403 (fora do papel Admin), 409 (já existe linha ativa para o par), 422 (`min_amount <= 0` ou `max_amount < min_amount`)

**PUT /admin/price-tables/{id}**, Edita `min_amount` / `max_amount` / `active` (Admin). Não reescreve `Solicitacao` já criada (snapshot). Erros: 403, 404, 409 (ativar colidiria com outra linha ativa do mesmo par), 422.

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
Idempotency-Key: <uuid>  (obrigatório em POST /proposals/{id}/accept, /services/{id}/approve,
                          /services/{id}/contest, /payments/{id}/release, RNF010)
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
