# 04: Modelo de Dados do MVP

> Revisado em 2026-08-17 para eliminar contradições com `foundation/00-domain-invariants.md` e `foundation/02-state-machine.md`, apontadas em análise crítica do PO. A versão anterior mantinha uma entidade `Contratacao` (proibida por INV-020), um `Pagamento` 1:1 simples (proibido por INV-040 a INV-045) e um `HistoricoImovel` de log raso (proibido por INV-060 a INV-062). Onde este documento e `00-domain-invariants.md` divergirem no futuro, o invariante vence, ver regra no topo de `00-domain-invariants.md`.

## Visão Geral

O domínio é centrado em uma **Solicitação de Serviço**, que recebe **Propostas**. O aceite de uma proposta é um **evento** (`ProposalAccepted`), não uma entidade, ele dispara a criação de um **Serviço**. O Serviço gera eventos de **Pagamento** (bounded context próprio), uma **Garantia** e ao menos uma **Intervention** no prontuário do **Property** (imóvel).

## Diagrama Conceitual

```
Usuário
 ├── Cliente
 └── Profissional ── PerfilProfissional (1:1)
        │
        ▼
Categoria ── TabelaPreco
        │
        ▼
Solicitação ──(property_id)──> Property ── Area ── Asset ── Intervention
        │
        ├── FotoSolicitacao
        └── Proposta ──(evento: ProposalAccepted, INV-020)──┐
                                                              ▼
                                                          Serviço
                                                           ├── Agenda
                                                           ├── Mensagem
                                                           ├── Avaliação
                                                           ├── Garantia
                                                           └── PaymentAuthorization
                                                                ├── PaymentEvent (append-only)
                                                                ├── PaymentSplit
                                                                ├── PaymentRefund
                                                                └── PaymentDispute
```

Não existe tabela `Contratacao`. O relacionamento Proposta → Serviço é direto (`servico.proposta_id`); o evento `ProposalAccepted` fica registrado em `Auditoria`, não em entidade própria (INV-020).

## Convenções

- UUID como chave primária em todas as entidades.
- Todo valor monetário é `INTEGER` em centavos (alinhado a `06-apis.md`), nunca `DECIMAL`/`FLOAT`.
- `criado_em`/`atualizado_em` em todas as tabelas.
- Enums deste documento são espelho direto dos estados definidos em `02-state-machine.md`, qualquer enum aqui que não tenha transição correspondente lá é bug de documentação, não uma opção válida.

## Entidades

### Usuario

`tipo` é único e fixo por registro, sem suporte a conta híbrida cliente+profissional no MVP (INV-001), se o mesmo CPF quiser os dois papéis, são dois cadastros distintos.

| Campo | Tipo | Obrigatório | Índice |
|---|---|---|---|
| id | UUID | Sim | PK |
| tipo | ENUM(`TipoUsuario`) | Sim | Sim |
| nome | VARCHAR(150) | Sim | |
| email | VARCHAR(150) | Sim | Unique |
| telefone | VARCHAR(20) | Sim | |
| senha_hash | VARCHAR | Sim | |
| foto | VARCHAR | Não | |
| status | ENUM(`StatusConta`) | Sim | Sim |
| criado_em | TIMESTAMP | Sim | |

**Exclusão (LGPD, ainda sem validação jurídica final, ver `foundation/04-decisions-pending.md`):** nunca hard-delete. `status = EXCLUIDA` (INV-003/state machine §6) + anonimização dos campos identificáveis (`nome`, `email`, `telefone`, `foto` substituídos por placeholder). Preserva integridade referencial com Auditoria, Pagamento e Avaliação, que são append-only e não podem perder FK.

**Relacionamentos**: 1:1 PerfilProfissional (só quando `tipo = PROFISSIONAL`), 1:N Solicitações, 1:N Propostas, 1:N Serviços, 1:N Avaliações, 1:N Endereço

### PerfilProfissional

> Adicionado em 2026-08-17 (issue #4). Materializa o "Nível de Confiança" do glossário (`01-visao-geral.md`) e as RN007/RN026. Métricas são **projeções cacheadas**, recalculadas por eventos de domínio (ver `foundation/05-trust-level.md` e policy P8 em `01-event-storm.md`). Só existe para `Usuario.tipo = PROFISSIONAL`.

| Campo | Tipo | Obrigatório | Índice |
|---|---|---|---|
| id | UUID | Sim | PK |
| usuario_id | UUID | Sim | Unique |
| nivel_confianca | ENUM(`NivelConfianca`) | Sim | Sim |
| servicos_aprovados | INTEGER | Sim | |
| nota_media_dez | INTEGER | Não | |
| taxa_cancelamento_pct | INTEGER | Sim | |
| reclamacoes_12m | INTEGER | Sim | |
| nivel_atualizado_em | TIMESTAMP | Sim | |

**Regras**:

- Criado no evento `ProfissionalVerificado` com `nivel_confianca = VERIFICADO` e contadores zerados.
- `nota_media_dez`: décimos da nota 1–5 (`45` = 4,5). `NULL` enquanto não houver avaliação `CLIENTE_AVALIA_PROFISSIONAL`.
- `taxa_cancelamento_pct`: inteiro 0–100, ver fórmula em `foundation/05-trust-level.md`.
- `reclamacoes_12m`: janela rolling de 365 dias; decrementada pelo job diário quando eventos saem da janela.
- Limiares de promoção/rebaixa: tabela em `foundation/05-trust-level.md`.

**Relacionamento**: Usuário (profissional) 1:1 PerfilProfissional

### Endereco

> Corrigido em 2026-08-17: só existe para o **profissional** (base de atuação, usada em RF010, busca por proximidade). `Property` não referencia mais esta tabela, ver abaixo. A versão anterior tinha `Property` 1:1 `Endereco`, e `Endereco.usuario_id` sobrevivia à venda do imóvel: o endereço do prontuário ficava preso ao dono anterior, mesmo depois de `PropertyOwnership` mudar de mão.

**Campos**: id, usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, latitude, longitude

**Relacionamento**: Usuário (profissional) 1:N Endereço

### Categoria

**Campos**: id, nome, descricao, ativo, template_escopo (JSONB)

**`template_escopo`**: schema dos campos estruturados que uma Solicitação dessa categoria precisa preencher. É o que torna `Solicitacao.escopo` (abaixo) estruturado em vez de texto livre, e o que faz `Proposta` não ter campo de escopo próprio ser suficiente para garantir comparabilidade (INV-080, OBJ-MVP-03). Sem template por categoria, "propostas comparáveis" é intenção declarada sem modelo, não um comportamento garantido pelo schema.

**Formato de cada campo do template** (chave = nome do campo em `Solicitacao.escopo`):

| Propriedade | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `tipo` | `int` \| `number` \| `enum` \| `bool` \| `string` | Sim | Tipo do valor em `escopo` |
| `obrigatorio` | bool | Sim | Se `true`, ausência no `escopo` da solicitação gera 422 |
| `rotulo` | string | Sim | Rótulo para o formulário no app |
| `valores` | string[] | Só se `tipo = enum` | Valores permitidos (UPPER_SNAKE_CASE) |
| `min` | number | Não | Limite inferior para `int`/`number` |

**Seed/fixture MVP**: definições canônicas em `database/fixtures/categorias_mvp.json`, carregáveis via `Database\Seeders\CategoriaSeeder::definitions()` (persistência no banco fica para a issue de Categorias).

#### Templates das 5 categorias do MVP (`01-visao-geral.md` §10)

##### Elétrica (`codigo: eletrica`)

| Campo | Tipo | Obrigatório | Valores / restrições |
|---|---|---|---|
| `tipo_servico` | enum | Sim | `INSTALACAO_PONTO`, `TROCA_DISJUNTOR`, `REVISAO_QUADRO`, `INSTALACAO_LUMINARIA`, `INSTALACAO_CHUVEIRO_ELETRICO`, `SUBSTITUICAO_FIO`, `DIAGNOSTICO` |
| `quantidade_pontos` | int | Sim | min 1 |

##### Hidráulica (`codigo: hidraulica`)

| Campo | Tipo | Obrigatório | Valores / restrições |
|---|---|---|---|
| `tipo_servico` | enum | Sim | `DESENTUPIMENTO`, `REPARO_VAZAMENTO`, `TROCA_TORNEIRA`, `TROCA_REGISTRO`, `INSTALACAO_LOUCA`, `TROCA_SIFAO`, `CAIXA_DAGUA`, `DIAGNOSTICO` |
| `quantidade_pontos` | int | Sim | min 1 |
| `ambiente` | enum | Sim | `BANHEIRO`, `COZINHA`, `AREA_SERVICO`, `AREA_EXTERNA`, `OUTRO` |

##### Pintura (`codigo: pintura`)

| Campo | Tipo | Obrigatório | Valores / restrições |
|---|---|---|---|
| `comodos` | int | Sim | min 1 |
| `area_m2` | number | Sim | min 1 |
| `tipo_tinta` | enum | Sim | `LATEX_PVA`, `ACRILICA`, `EPOXI`, `ESMALTE` |
| `paredes_ou_teto` | enum | Sim | `PAREDES`, `TETO`, `PAREDES_E_TETO` |

##### Pequenos Reparos (`codigo: pequenos_reparos`)

| Campo | Tipo | Obrigatório | Valores / restrições |
|---|---|---|---|
| `tipo_reparo` | enum | Sim | `PORTA_JANELA`, `FECHADURA`, `PISO_REVESTIMENTO`, `AZULEJO`, `GESSO`, `PERSIANA`, `MOVEL_FIXO`, `OUTRO` |
| `quantidade` | int | Sim | min 1 |
| `area_m2` | number | Não | min 0 (opcional; útil para reparos de superfície) |

##### Montagem (`codigo: montagem`)

| Campo | Tipo | Obrigatório | Valores / restrições |
|---|---|---|---|
| `tipo_item` | enum | Sim | `MOVEL_PRONTO`, `MOVEL_SOB_MEDIDA`, `ELETRODOMESTICO`, `SUPORTE_TV`, `PRATELEIRA`, `OUTRO` |
| `quantidade` | int | Sim | min 1 |
| `precisa_fixacao_parede` | bool | Sim | `true` ou `false` |

**Exemplo de `escopo` válido (Pintura)**:

```json
{
  "comodos": 2,
  "area_m2": 35.5,
  "tipo_tinta": "LATEX_PVA",
  "paredes_ou_teto": "PAREDES_E_TETO"
}
```

Campos do template **podem** declarar `ajuste_preco` opcional (fator em basis points, `10000` = 1.0). Campo sem `ajuste_preco` não altera a faixa estimada. Schema e fórmula em `10-motor-precificacao.md` §2.2.

### TabelaPreco

> Adicionado em 2026-08-17 (issue #2). Bootstrap da estimativa de preço sem histórico próprio de serviços na cidade piloto. Heurística, pesquisa de cold start e fórmula em `10-motor-precificacao.md`. Não é proposta e não é preço cobrável; o valor da transação continua sendo `Proposta.valor`.

**Campos**: id, categoria_id, cidade, valor_min (INTEGER, centavos), valor_max (INTEGER, centavos), ativo, criado_em, atualizado_em

**Índice obrigatório**: `UNIQUE (categoria_id, cidade) WHERE ativo = true`, no máximo uma linha ativa por par categoria+cidade.

**Regras**:
- `valor_min > 0` e `valor_max >= valor_min`.
- Linha com `ativo = false` não entra no cálculo.
- Sem linha ativa para `categoria_id` + `Property.cidade` da solicitação, a estimativa **falha**: `POST /requests` e `POST /requests/estimate` respondem 422 com código `PRECO_TABELA_AUSENTE`. Não inventa número.
- Editar `valor_min`/`valor_max` depois **não** reescreve `Solicitacao` já criada (snapshot em `faixa_preco_*` abaixo).
- Só Admin cria/edita (RF028 / `06-apis.md` `/admin/price-tables`).

**Relacionamentos**: Categoria N:1 · Solicitação 1:N (via `tabela_preco_id` do snapshot)

### Property (Imóvel)

> Renomeado de `Imovel` para alinhar com `06-apis.md` (`/properties`, `property_id`) e com INV-061/062. Antes ligado a `cliente_id` como FK fixa, isso contradizia o glossário ("vinculado ao endereço/unidade, não à pessoa") e quebrava o prontuário na venda do imóvel. Agora o vínculo com o dono é mutável (via `PropertyOwnership`) e separado da identidade do registro.
>
> Corrigido em 2026-08-17: o endereço passou a ser **campo próprio de Property**, não mais FK para `Endereco`. A versão anterior (`Property` 1:1 `Endereco`, com `Endereco.usuario_id`) deixava o endereço do imóvel pendurado no dono anterior depois de uma venda, o desacoplamento via `PropertyOwnership` não tinha efeito nenhum enquanto o endereço em si continuasse amarrado a um usuário.
>
> Corrigido em 2026-08-17 (3ª revisão do PO): nada impedia dois registros de `Property` para a mesma casa (mesmo endereço cadastrado duas vezes fragmenta o prontuário, que é o próprio diferencial competitivo do produto, OBJ-NEG-02). Adicionado `chave_endereco` com índice único.

**Campos**: id, cep, logradouro, numero, complemento, bairro, cidade, estado, latitude, longitude, apelido, chave_endereco (gerado, ver abaixo)

**`chave_endereco`**: normalização de `cep + numero + complemento` (maiúsculas, sem acento, sem pontuação, trim), ex.: `01310200|100|APTO101`. Índice `UNIQUE (chave_endereco)`. **Limite conhecido**: normalização de string não resolve variação livre de texto em `complemento` (ex.: "Bloco A Apto 101" vs. "BL A AP 101" geram chaves diferentes para a mesma unidade), mitiga duplicata óbvia, não garante deduplicação perfeita sem autocomplete/validação de endereço na criação (fora de escopo do MVP).

**Relacionamentos**: 1:N Area · 1:N PropertyOwnership · 1:N PropertyOwnershipTransfer

### PropertyOwnership

> Histórico de posse, permite trocar o dono (venda do imóvel) sem apagar nem reatribuir o histórico de `Intervention`, que referencia `Asset`/`Area`/`Property`, nunca o cliente diretamente.

**Campos**: id, property_id, cliente_id, desde, ate (NULL = dono atual)

**Regra de integridade**: no máximo um registro com `ate IS NULL` por `property_id`.

**Quem escreve aqui**: só o fluxo de `PropertyOwnershipTransfer` abaixo (aceite do novo dono). Corrigido em 2026-08-17 (3ª revisão do PO), a versão anterior criava a tabela mas nenhum endpoint escrevia nela; a venda do imóvel, motivo original da refatoração, não tinha caminho executável.

### PropertyOwnershipTransfer

> Transferência de posse nunca é unilateral (INV-064), dono atual só inicia, novo dono precisa aceitar. Evita que erro de digitação ou má-fé transfira um prontuário inteiro sem o outro lado saber.

**Campos**: id, property_id, de_cliente_id, para_cliente_id (nulo se `para_email` ainda sem conta na plataforma), para_email, status (`PENDENTE | ACEITO | RECUSADO | EXPIRADO`), criado_em, expira_em

**Regra**: ao `ACEITO`, fecha o `PropertyOwnership.ate` do `de_cliente_id` (`= now()`) e cria um novo `PropertyOwnership` com `cliente_id = para_cliente_id` e `ate = NULL`, na mesma transação. `de_cliente_id` deve ser o dono vigente no momento da criação (mesma checagem de INV-014, aplicada aqui a `PropertyOwnershipTransfer` em vez de `Solicitação`).

### Area

**Campos**: id, property_id, nome (ex.: "Cozinha"; usar `"Não especificado"` como fallback, INV-061)

### Asset

**Campos**: id, area_id, nome/tipo (ex.: "Torneira"; mesmo fallback de Area, INV-061)

### Intervention

> Substitui `HistoricoImovel`. Implementa INV-060/061/062: nunca solta (sempre referencia Asset dentro de Area dentro de Property) e sempre carrega origem.

**Campos**: id, asset_id, servico_id (NULL se `origem != PLATAFORMA`), data, categoria, resumo, origem (ENUM `PLATAFORMA | MANUAL | IMPORTADO`), confiabilidade (derivada da origem, não editável manualmente)

**Regra de criação**: todo `Serviço` que atinge `APROVADO` (INV-060, não existe estado `FINALIZADO`, corrigido em 2026-08-17) gera automaticamente Area/Asset "Não especificado" quando a granularidade não foi capturada no fluxo, e uma `Intervention` com `origem = PLATAFORMA`.

### Solicitacao

**Campos**: id, cliente_id, categoria_id, property_id, descricao, escopo (JSONB), status, data_desejada, faixa_preco_min (INTEGER, centavos), faixa_preco_max (INTEGER, centavos), faixa_preco_fator_bp (INTEGER), tabela_preco_id, criado_em

> Campo renomeado de `endereco_id` para `property_id`, a versão anterior divergia de `06-apis.md`, que já usa `property_id` em `POST /requests`. Toda solicitação nasce vinculada a um imóvel (que carrega o endereço), não a um endereço solto, porque o prontuário (`Intervention`) precisa de um `Property` para existir.
>
> **`escopo`** (adicionado em 2026-08-17, quarta revisão do PO, modela INV-080): estrutura preenchida pelo cliente na criação, validada contra `Categoria.template_escopo` (campos obrigatórios do template presentes). `descricao` continua existindo como texto livre complementar, mas o escopo comparável entre propostas é este campo estruturado, não a descrição. É fixo depois de criada a solicitação (editar escopo depois de já existirem propostas quebraria a comparabilidade que gerou; se precisar mudar, cancela e recria).
>
> **`faixa_preco_*` / `tabela_preco_id`** (adicionado em 2026-08-17, issue #2, fecha OBJ-MVP-01 e OBJ-TEC-02): snapshot da estimativa no instante do cálculo (`10-motor-precificacao.md`). `faixa_preco_min < faixa_preco_max` sempre (intervalo, nunca ponto único). Não é input do cliente nem do profissional; `POST /requests` ignora qualquer faixa enviada no body. Recálculo só em `PUT /requests/{id}` quando `escopo`/`categoria_id`/`property_id` mudam **e** ainda não há proposta (mesmo recorte de INV-080); se já houver proposta, o snapshot fica congelado junto com o escopo.

**Regra (INV-080)**: `Proposta` não tem campo de escopo (ver entidade abaixo), o profissional responde sobre o `escopo` da `Solicitacao` como está, só varia valor/prazo/garantia_dias/observacoes. A ausência do campo na tabela é o que impede a violação, não é só validação de API.

**Relacionamentos**: Cliente, Categoria, Property, TabelaPreco, Fotos, Propostas

### FotoSolicitacao

**Campos**: id, solicitacao_id, url, ordem

### Proposta

**Campos**: id, solicitacao_id, profissional_id, valor (INTEGER, centavos), prazo_dias, garantia_dias, observacoes, status

**Índice obrigatório**: `UNIQUE (solicitacao_id) WHERE status = 'ACEITA'`, é o mecanismo físico que garante INV-010 (no máximo uma proposta aceita por solicitação); sem ele a invariante depende só de disciplina de aplicação.

### Servico

**Campos**: id, proposta_id, inicio, fim, status

> `contratacao_id` removido, não existe `Contratacao` (INV-020). O Serviço referencia a Proposta aceita diretamente; o evento `ProposalAccepted` que autorizou sua criação fica em `Auditoria`. Não existe entidade `Contract` neste modelo, decisão consciente enquanto não houver artefato jurídico próprio (assinatura eletrônica, aditivo), se isso surgir, nasce como entidade nova e explícita, não reaproveitando o antigo conceito de `Contratacao` (INV-022).

### Agenda

**Campos**: id, servico_id, data, hora, observacoes

### Mensagem

**Campos**: id, servico_id, remetente_id, texto, anexo, enviado_em

> Ver `09-mecanismo-antidesintermediacao.md` para o mascaramento de contato exigido neste fluxo (OBJ-NEG-03).

### Avaliação

> A versão anterior era unilateral (só cliente avalia profissional), o que é fraco para retenção do lado profissional num marketplace de dois lados.

**Campos**: id, servico_id, autor_id, alvo_id, direcao (ENUM `CLIENTE_AVALIA_PROFISSIONAL | PROFISSIONAL_AVALIA_CLIENTE`), nota, comentario

**Validação**: nota entre 1 e 5. No máximo uma avaliação por `(servico_id, direcao)`.

### Garantia

**Campos**: id, servico_id, inicio, fim, status (`StatusGarantia`), responsavel_financeiro (fixo `PROFISSIONAL`, INV-053/B001)

**Revisita dentro da garantia**: se o acionamento gera um novo `Serviço` do mesmo profissional para a mesma causa/escopo já coberto, esse `Serviço` não cria `PaymentAuthorization` nova, é uma revisita sem cobrança ao cliente (INV-033). Cancelamento dessa revisita segue B003 (`foundation/03-cancellation-rules.md`) sem nova autorização para estornar.

**Regra de encerramento (INV-053, decisão provisória de B001, `adr/ADR-003-garantia.md`)**: quando `status` sai de `ATIVA` (vira `EXPIRADA` sem acionamento, ou `ENCERRADA` após `ACIONADA` resolvida), **não dispara nenhum evento financeiro**. A plataforma já repassou 100% do `valor_profissional` ao profissional 72h após aprovação (`adr/ADR-004-prazo-aceite-automatico.md`), não há reserva a liberar. Se `ACIONADA`, a resolução é entre profissional e cliente, a plataforma só media (não gera `PaymentRefund` da própria plataforma). Não modelar fundo garantidor/caução/reserva enquanto B001 não tiver parecer jurídico definitivo.

## Bounded Context: Payment (INV-040 a INV-045)

> Substitui integralmente a antiga tabela `Pagamento` (`status: PENDENTE/RETIDO/LIBERADO/PAGO`), que usava vocabulário de escrow, rejeitado por `ADR-002-financeiro.md`. Modelo abaixo é o de eventos imutáveis já descrito em `00-domain-invariants.md` e nas transições de `02-state-machine.md` §4.

### PaymentAuthorization

> Corrigido em 2026-08-17: cardinalidade era `Serviço` 1:1 `PaymentAuthorization`. Isso tornava reautorização impossível, autorização de cartão expira em ~5-7 dias, mas um serviço pode ser agendado para 2+ semanas depois. Sem caminho de volta, o serviço virava órfão financeiro assim que a autorização expirasse antes da conclusão. Agora é `Serviço` 1:N `PaymentAuthorization` (INV-046).

**Campos**: id, servico_id, valor (INTEGER, centavos), metodo (`MetodoPagamento`: `CARTAO | PIX`), status (`StatusPaymentAuthorization`: `AUTORIZADO | CAPTURADO | CANCELADO | EXPIRADO`), criado_em, expira_em

**Índice obrigatório**: `UNIQUE (servico_id) WHERE status = 'AUTORIZADO'`, no máximo uma autorização ativa por serviço a qualquer momento, física, não só de aplicação (mesmo padrão do índice parcial de Proposta).

**Regra**: toda autorização termina em `CAPTURADO`, `CANCELADO` ou `EXPIRADO`, nunca fica em `AUTORIZADO` indefinidamente (INV-042). `expira_em` é o campo que sustenta essa regra via job. Se expira e o Serviço ainda não está `CANCELADO`/`APROVADO`, o job cria automaticamente uma nova `PaymentAuthorization`, registrado como evento `REAUTORIZADO` em `PaymentEvent` (INV-046).

**Regra (Pix, `adr/ADR-005-gateway-pagamento.md`)**: `metodo = PIX` nasce `CAPTURADO` (captura imediata no Asaas); `expira_em` é nulo; INV-046 não dispara. `metodo = CARTAO` nasce `AUTORIZADO`. Gateway do MVP: Asaas (B006).

**Cancelamento Cenário B (B003, `foundation/03-cancellation-rules.md`):** enquanto Serviço `AGENDADO`, cancelamento pelo cliente pode gerar captura parcial (= multa) ou cancelamento integral (multa zero):

1. Calcula `valor_multa` a partir dos parâmetros `CANCELLATION_PENALTY_*` em `Configuração`.
2. **`valor_multa = 0`, cartão:** transição `AUTORIZADO → CANCELADO`, `PaymentEvent` tipo `CANCELADO` (libera 100% da autorização no gateway).
3. **`valor_multa > 0`, cartão, gateway com captura parcial (Asaas ainda não assume parcial, `adr/ADR-005-gateway-pagamento.md`):**
   - Gateway captura `valor_multa` e libera o saldo restante da mesma autorização.
   - `PaymentEvent` tipo `CAPTURADO` com `payload.motivo = CANCELAMENTO_MULTA`, `payload.valor = valor_multa`.
   - `PaymentAuthorization.status = CAPTURADO` (terminal, INV-042; valor retido = multa).
   - `PaymentSplit` gerado sobre esse evento de captura (comissão sobre a multa).
4. **`valor_multa > 0`, cartão, gateway sem captura parcial (fallback):** captura integral via gateway → `PaymentEvent CAPTURADO` → `PaymentEvent REEMBOLSADO` com `payload.valor = valor - valor_multa` (libera ao cliente o que não é multa). Mesmo `status` terminal `CAPTURADO`.
5. **Pix (já `CAPTURADO` no aceite):** Cancelamento Cenário B usa `PaymentRefund` parcial (`valor - valor_multa`) em vez de captura parcial; multa retida na plataforma até o `REPASSADO` da parcela do profissional.

`PaymentRefund` (INV-043) **não** se aplica ao caminho cartão com autorização ainda `AUTORIZADO`; o evento correto é `CAPTURADO` parcial ou `CANCELADO` integral.

### PaymentEvent

> Log append-only. Fonte de verdade do histórico financeiro, `PaymentAuthorization.status` é uma projeção/cache do último evento, nunca editada diretamente (INV-040).

**Campos**: id, payment_authorization_id, tipo (`AUTORIZADO | CAPTURADO | REPASSADO | CANCELADO | EXPIRADO | REEMBOLSADO | REAUTORIZADO`), payload (JSON), criado_em

> `REAUTORIZADO` marca o evento em que uma `PaymentAuthorization` expirada gera a próxima (INV-046); o `payload` referencia o `id` da autorização anterior.

**Regra de integridade**: sem `UPDATE`/`DELETE`, só `INSERT`.

### PaymentSplit

> Revisado em 2026-08-17 (5ª revisão do PO): a proposta de reter uma fração do repasse do profissional como reserva de garantia (versões anteriores deste documento) foi descartada pela decisão provisória de B001 (`adr/ADR-003-garantia.md`), reintroduzia o enquadramento de escrow que `adr/ADR-002-financeiro.md` rejeitou. `valor_profissional` volta a ser um valor único, sem reserva.

**Campos**: id, payment_event_id (o evento de captura), valor_profissional, valor_plataforma, aliquota_vigente

**Regra**: calculado no momento da captura com a alíquota vigente naquele instante; alterar a comissão depois não recalcula splits antigos (INV-044). `valor_profissional` é repassado integralmente ao profissional na janela de 72h (`adr/ADR-004-prazo-aceite-automatico.md`), sem retenção adicional (INV-053). Em Pix (`adr/ADR-005-gateway-pagamento.md`), a captura é o aceite da proposta: o `PaymentSplit` nasce aí, mas o dinheiro só sai da conta Asaas da plataforma no evento `REPASSADO` (transferência interna, sem `splits` na cobrança Pix).

### PaymentRefund

**Campos**: id, payment_event_id (deve referenciar um evento `CAPTURADO`), valor, motivo, criado_em

**Regra**: só existe sobre valor já capturado. Sobre valor apenas autorizado, o evento correto é `CANCELADO`, não um `PaymentRefund` (INV-043).

### PaymentDispute

**Campos**: id, servico_id, tipo (`CONTESTACAO_CONCLUSAO | CANCELAMENTO_EXECUCAO`), status (`ABERTA | RESOLVIDA`), aberta_em, resolvida_em, resolvida_por_id (UUID, Admin), resultado (`APROVADO | CANCELADO`, preenchido na resolução), justificativa (obrigatória na resolução, INV-070)

**Regra**: enquanto `ABERTA`, bloqueia geração de evento `REPASSADO`, mas não bloqueia novos `PaymentEvent` de outro tipo (INV-045). Pausa o timer de aceite automático (`AUTO_APPROVAL_HOURS`) enquanto `CONTESTACAO_CONCLUSAO` estiver aberta.

**Resolução (B003, `foundation/03-cancellation-rules.md`):**
- `CONTESTACAO_CONCLUSAO` + `APROVADO` → Serviço `APROVADO`, captura integral.
- `CONTESTACAO_CONCLUSAO` + `CANCELADO` → Serviço `CANCELADO`, autorização `AUTORIZADO → CANCELADO`.
- `CANCELAMENTO_EXECUCAO` + `CANCELADO` → Serviço `CANCELADO`, autorização liberada integralmente.
- `CANCELAMENTO_EXECUCAO` + `APROVADO` → Serviço retorna a `EM_ANDAMENTO` (pedido de cancelamento negado).
- Timeout após `DISPUTE_MEDIATION_DAYS` (7 dias, `Configuração`): resolução automática conforme tabela em `03-cancellation-rules.md`.

## Auditoria

**Campos**: id, usuario_id, acao, entidade, id_entidade, data, ip, justificativa (obrigatório quando `acao` é liberação/captura administrativa fora do fluxo normal, INV-041)

## Tabelas Auxiliares

### Configuração
Parâmetros globais: comissão (%), prazo de garantia padrão, `AUTO_APPROVAL_HOURS` (tempo limite para aceite automático, 72h, `adr/ADR-004-prazo-aceite-automatico.md`), `DISPUTE_MEDIATION_DAYS` (prazo máximo de mediação de disputa, 7 dias, B003), `CANCELLATION_PENALTY_TIER1_HOURS` (48), `CANCELLATION_PENALTY_TIER1_PERCENT` (10), `CANCELLATION_PENALTY_TIER2_HOURS` (24), `CANCELLATION_PENALTY_TIER2_PERCENT` (25), `CANCELLATION_PENALTY_TIER3_PERCENT` (50), raio máximo de atendimento, `PRECO_ARREDONDAMENTO_CENTAVOS` (default `1000` = R$ 10, usado na heurística de `10-motor-precificacao.md` §2.3).

### Notificação
**Campos**: usuario_id, titulo, mensagem, lida, data

### DocumentoProfissional

Validação documental do profissional (RF002). No MVP a revisão é **manual pelo Admin** (sem time de verificação automatizada). Cada upload gera um registro; reenvio após reprovação cria nova linha (histórico preservado).

**Campos**

| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| id | UUID | Sim | PK |
| profissional_id | UUID | Sim | FK `Usuario` (`tipo = PROFISSIONAL`) |
| tipo | ENUM(`TipoDocumentoProfissional`) | Sim | Slot documental (ver matriz abaixo) |
| arquivo | VARCHAR | Sim | URL no object storage (S3 compatível, `05-arquitetura.md`) |
| status | ENUM(`StatusDocumentoProfissional`) | Sim | Ciclo de revisão (§7 de `02-state-machine.md`) |
| motivo_rejeicao | VARCHAR(500) | Não | Obrigatório quando `status = REJEITADO` |
| revisado_por_id | UUID | Não | Admin que aprovou/reprovou |
| revisado_em | TIMESTAMP | Não | Momento da decisão |
| apolice_numero | VARCHAR | Só se `tipo = SEGURO_RC` | Número da apólice (B005) |
| vigencia_inicio | DATE | Só se `tipo = SEGURO_RC` | Início da vigência |
| vigencia_fim | DATE | Só se `tipo = SEGURO_RC` | Fim da vigência |
| criado_em | TIMESTAMP | Sim | |
| atualizado_em | TIMESTAMP | Sim | |

**Relacionamento**: Profissional (`Usuario`) 1:N `DocumentoProfissional`

#### Documentos exigidos (MVP)

Lista **base** (todas as categorias do MVP) + exigência **adicional** só para profissionais que declaram atender **Elétrica** (`codigo: eletrica`). As demais categorias iniciais (Hidráulica, Pintura, Pequenos Reparos, Montagem) não têm exigência regulatória diferenciada além da base.

| `TipoDocumentoProfissional` | Obrigatório | Critério de aprovação (Admin, MVP) |
|---|---|---|
| `IDENTIDADE_FISCAL` | Todos | CPF (PF) ou CNPJ (PJ) legível, nome compatível com cadastro, documento dentro da validade |
| `COMPROVANTE_ENDERECO` | Todos | Emitido nos últimos 90 dias; endereço compatível com `Endereco` de atuação cadastrado |
| `SELFIE_IDENTIDADE` | Todos | Rosto visível segurando o mesmo documento de `IDENTIDADE_FISCAL` |
| `SEGURO_RC` | Todos | Apólice de responsabilidade civil vigente, com comprovante validado por Admin (decisão provisória B005) |
| `CERTIFICADO_NR10` | Só se declara categoria Elétrica | Certificado NR-10 válido (curso de segurança em instalações elétricas); nome compatível com cadastro |

**Cálculo da exigência**: união da lista base com os tipos adicionais das categorias declaradas no perfil do profissional (`categorias_atendidas`, ver RF002 em `02-funcionalidades.md`). Ex.: profissional que atende Pintura + Elétrica precisa dos 5 tipos acima.

**Regra de slot**: para cada `tipo` exigido, conta como satisfeito o registro **mais recente** com `status = APROVADO`. Reenvios após `REJEITADO` geram nova linha; a reprovada permanece no histórico.

**Vigência do `SEGURO_RC` (B005):** job diário marca `status = VENCIDO` quando `vigencia_fim < hoje`; profissional com `SEGURO_RC` vencido não recebe novas solicitações até revalidar.

#### Transição `Conta.status` → `ATIVA` (INV-002)

Profissional nasce com `Usuario.status = PENDENTE_VERIFICACAO` no cadastro (RF002). A conta só transiciona para `ATIVA` quando **todas** as condições abaixo forem verdadeiras:

1. O profissional declarou ao menos uma categoria em `categorias_atendidas`.
2. Para **cada** `TipoDocumentoProfissional` exigido (base + adicionais das categorias declaradas), existe registro com `status = APROVADO` (slot satisfeito, regra acima).
3. Não há registro exigido pendente de revisão (`status = PENDENTE`) para slot ainda não aprovado.

A transição é disparada pelo **Admin** ao aprovar o último documento pendente (checagem de completude) ou por job após essa aprovação. Emite evento `ProfissionalVerificado` (`01-event-storm.md`). Enquanto `PENDENTE_VERIFICACAO`, o profissional **não** recebe solicitações nem envia propostas (INV-002, RN001).

**Reprovação**: Admin define `REJEITADO` + `motivo_rejeicao`; `Conta.status` permanece `PENDENTE_VERIFICACAO` até reenvio e aprovação. Suspender ou bloquear conta já `ATIVA` segue §6 de `02-state-machine.md` (INV-003).

> Subconta Asaas (`walletId`) para repasse (`REPASSADO`, `adr/ADR-005-gateway-pagamento.md`) é onboarding financeiro **paralelo**, não gate de `Conta.status = ATIVA` para receber solicitações.

## Relacionamentos

| Origem | Destino | Cardinalidade |
|---|---|---|
| Usuário | PerfilProfissional | 1:1 (profissional) |
| Usuário | Endereço | 1:N |
| Usuário | Solicitação | 1:N |
| Categoria | Solicitação | 1:N |
| Categoria | TabelaPreco | 1:N |
| TabelaPreco | Solicitação | 1:N (snapshot; linha pode ser editada depois sem mutar solicitações antigas) |
| Property | Area | 1:N |
| Property | PropertyOwnership | 1:N |
| Property | PropertyOwnershipTransfer | 1:N |
| Area | Asset | 1:N |
| Asset | Intervention | 1:N |
| Solicitação | Property | N:1 |
| Solicitação | Foto | 1:N |
| Solicitação | Proposta | 1:N |
| Proposta | Serviço | 1:1 (opcional, só existe se aceita) |
| Serviço | PaymentAuthorization | 1:N (no máx. uma `AUTORIZADO` por vez, INV-046) |
| Serviço | Garantia | 1:1 |
| Serviço | Avaliação | 1:N (máx. 2, uma por direção) |
| Serviço | Intervention | 1:N |
| PaymentAuthorization | PaymentEvent | 1:N |
| PaymentEvent (captura) | PaymentSplit | 1:1 |
| PaymentEvent (captura) | PaymentRefund | 1:N |

## Enumerações

Espelho de `02-state-machine.md`, não editar aqui sem editar lá.

**TipoUsuario**: `CLIENTE`, `PROFISSIONAL`, `ADMIN`

**StatusConta**: `PENDENTE_VERIFICACAO`, `ATIVA`, `SUSPENSA`, `BLOQUEADA`, `EXCLUIDA`

**StatusSolicitacao**: `CRIADA`, `ABERTA`, `RECEBENDO_PROPOSTAS`, `CONTRATADA`, `CANCELADA`, `EXPIRADA`

**StatusProposta**: `ENVIADA`, `ACEITA`, `RECUSADA`, `RETIRADA`

> `CANCELADA` removida, `02-state-machine.md` só define `Retirada` (ação do profissional antes do aceite). Recusa automática por aceite de outra proposta é `RECUSADA` (INV-011).

**StatusServico**: `AGENDADO`, `EM_ANDAMENTO`, `AGUARDANDO_APROVACAO`, `APROVADO`, `EM_CONTESTACAO`, `CANCELADO`

> Substituídos `CONCLUIDO`/`FINALIZADO`, que não existiam na state machine. `APROVADO` dispara captura **integral** de **cartão** (Pix já foi capturado no aceite, `adr/ADR-005-gateway-pagamento.md`) e `Garantia`. `CANCELADO` no Cenário B dispara captura **parcial** da multa (INV-032/INV-041), sem garantia.

**StatusPaymentAuthorization**: `AUTORIZADO`, `CAPTURADO`, `CANCELADO`, `EXPIRADO`

**MetodoPagamento**: `CARTAO`, `PIX` (MVP, `adr/ADR-005-gateway-pagamento.md`)

**TipoPaymentEvent**: `AUTORIZADO`, `CAPTURADO`, `REPASSADO`, `CANCELADO`, `EXPIRADO`, `REEMBOLSADO`, `REAUTORIZADO`

**StatusGarantia**: `ATIVA`, `EXPIRADA`, `ACIONADA`, `ENCERRADA`

> `ENCERRADA` adicionada, faltava para representar a transição final de `Acionada` em `02-state-machine.md` §5.

**OrigemIntervention**: `PLATAFORMA`, `MANUAL`, `IMPORTADO`

**StatusPropertyOwnershipTransfer**: `PENDENTE`, `ACEITO`, `RECUSADO`, `EXPIRADO`

**NivelConfianca**: `VERIFICADO`, `BRONZE`, `PRATA`, `OURO`, `ELITE`

> Ordem de exibição/ordenação segue a sequência acima (crescente). Limiares de progressão em `foundation/05-trust-level.md`.

**TipoDocumentoProfissional**: `IDENTIDADE_FISCAL`, `COMPROVANTE_ENDERECO`, `SELFIE_IDENTIDADE`, `SEGURO_RC`, `CERTIFICADO_NR10`

**StatusDocumentoProfissional**: `PENDENTE`, `APROVADO`, `REJEITADO`, `VENCIDO` (§7 de `02-state-machine.md`; `VENCIDO` aplica-se a `SEGURO_RC`, B005)

## Índices Recomendados

**Usuário**: email (Unique), telefone, tipo

**PerfilProfissional**: usuario_id (Unique), nivel_confianca, composto `(nivel_confianca DESC, nota_media_dez DESC)` para ordenação em RF010

**Solicitação**: cliente_id, categoria_id, property_id, tabela_preco_id, status, criado_em

**TabelaPreco**: categoria_id, cidade, ativo, `UNIQUE (categoria_id, cidade) WHERE ativo = true`

**Proposta**: profissional_id, solicitacao_id, status, `UNIQUE (solicitacao_id) WHERE status = 'ACEITA'`

**Serviço**: status, proposta_id

**PaymentAuthorization**: servico_id, status, expira_em, `UNIQUE (servico_id) WHERE status = 'AUTORIZADO'`

**PaymentEvent**: payment_authorization_id, tipo, criado_em

**Property**: latitude, longitude (bounding box, ver seção "Busca geográfica"), `UNIQUE (chave_endereco)`

**PropertyOwnershipTransfer**: property_id, para_cliente_id, para_email, status

**Intervention**: asset_id, servico_id, origem

## Busca geográfica (P0, sem PostGIS, ver `08-planejamento.md`)

"Localizar profissionais próximos" é P0 mas PostGIS é pós-MVP (dívida técnica aceita). Sem índice geoespacial, a única estratégia viável no MVP é bounding box comparando `latitude`/`longitude` de `Property` (localização da solicitação) contra `latitude`/`longitude` de `Endereco` do profissional (índice B-tree composto em cada tabela, não `GiST`). **Limitação conhecida e documentada**: não escala bem para alta densidade de profissionais nem para busca por raio preciso, reavaliar antes de qualquer expansão de cidade que dependa disso.

## Regras de Integridade

- Toda solicitação pertence a um cliente e a um property, e o cliente deve ser o dono vigente do property (`PropertyOwnership.ate IS NULL` com `cliente_id` igual ao da solicitação), INV-014.
- Toda solicitação persistida carrega snapshot `faixa_preco_min`, `faixa_preco_max`, `faixa_preco_fator_bp` e `tabela_preco_id` da linha ativa usada no cálculo; `faixa_preco_min < faixa_preco_max`. Sem `TabelaPreco` ativa para o par categoria+cidade, a solicitação não é criada.
- Toda proposta pertence a uma solicitação.
- Só o cliente dono da solicitação pode aceitar uma proposta dela, `POST /proposals/{id}/accept` checa `solicitacao.cliente_id` contra o usuário autenticado (ownership, INV-013).
- No máximo uma proposta por solicitação pode estar `ACEITA` (índice parcial, não só validação de aplicação).
- Todo serviço nasce de uma proposta aceita (nunca manual fora do fluxo administrativo auditado) e pertence, transitivamente, a exatamente uma solicitação, via `proposta_id` (INV-030).
- Todo serviço possui no máximo uma `PaymentAuthorization`.
- Todo serviço que chega a `APROVADO` gera exatamente uma garantia.
- Toda avaliação pertence a um serviço em `APROVADO`.
- Toda `Intervention` referencia um `Asset`, nunca solto.

## Pendências para Validação

- Um cliente poderá cadastrar vários imóveis no MVP ou apenas um? (Não bloqueia o modelo, `Property`/`PropertyOwnership` já suportam N:N ao longo do tempo.)
- O chat será persistido indefinidamente?
- Fotos serão armazenadas localmente ou em object storage?
- Será permitido múltiplos pagamentos por serviço (parcelamento)? Hoje o modelo assume `Serviço` 1:1 `PaymentAuthorization`.
- Como serão tratadas revisitas durante a garantia?
- Haverá emissão de nota fiscal pela plataforma?
- A contratação poderá envolver mais de um profissional?
- Exclusão LGPD por anonimização (proposta acima) precisa de validação jurídica, mesma pendência de B001/ADR-002.
- Valor mínimo de cobertura da apólice RC e demais documentos obrigatórios além de `SEGURO_RC` (identidade, certificações por categoria) dependem de parecer jurídico definitivo de B005.
- Valores reais de `TabelaPreco` na cidade piloto (os exemplos de `10-motor-precificacao.md` §2.1 são chutes operacionais, não copiar para produção sem revisão).
- Quais campos de cada `template_escopo` carregam `ajuste_preco` no lançamento.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Versão original (pré-DDD), com `Contratacao`, `Pagamento` simples e `HistoricoImovel`. |
| 2026-08-17 (1ª passada) | Reescrita completa contra `00-domain-invariants.md`/`02-state-machine.md`: remove `Contratacao`, introduz bounded context `Payment` (5 entidades), substitui `HistoricoImovel` por `Property/Area/Asset/Intervention`, corrige enums divergentes da state machine, renomeia `Imovel`→`Property` e `endereco_id`→`property_id` em Solicitação, adiciona `PropertyOwnership`, avaliação bidirecional, índice parcial de proposta aceita. |
| 2026-08-17 (4ª passada) | Rebaixa INV-053 de invariante fechada para proposta (ADR-002/003 reabertos, ver `foundation/00-domain-invariants.md`); referencia INV-001/013/022/030/033 (estavam órfãs, sem nenhum ponto de aplicação); modela INV-080 (`Categoria.template_escopo` + `Solicitacao.escopo`, `Proposta` sem campo de escopo por construção). |
| 2026-08-17 (6ª passada) | Define `template_escopo` concreto das 5 categorias MVP (elétrica, hidráulica, pintura, pequenos reparos, montagem); fixture em `database/fixtures/categorias_mvp.json` + `CategoriaSeeder`. |
| 2026-08-17 (3ª passada) | Adiciona `chave_endereco` (CEP+numero+complemento normalizados, `UNIQUE`) em Property (INV-063, corrige duplicidade de imóvel); adiciona `PropertyOwnershipTransfer` e regra de aceite explícito (INV-064, corrige `PropertyOwnership` sem caminho executável); corrige INV-031 (`CONCLUIDO`→`APROVADO`, mesma classe de bug de INV-060). |
| 2026-08-17 (2ª passada) | Corrige 4 contradições introduzidas na 1ª passada: `PaymentAuthorization` vira 1:N com reautorização (INV-046, não mais órfão financeiro em autorização expirada); `Property` passa a ter endereço próprio em vez de FK para `Endereco.usuario_id` (venda de imóvel não deixa mais o endereço preso ao dono anterior); `INV-060` corrigida para `APROVADO` (referenciava `FINALIZADO`, estado inexistente); adiciona INV-014 (ownership de Solicitação via `PropertyOwnership`). |
| 2026-08-17 | `PaymentAuthorization.metodo` fecha `CARTAO | PIX` (`adr/ADR-005-gateway-pagamento.md`, B006): Pix nasce `CAPTURADO`, cartão nasce `AUTORIZADO`. |
| 2026-08-17 (issue #2) | Adiciona `TabelaPreco` (categoria+cidade, editável por Admin) e snapshot `faixa_preco_min`/`faixa_preco_max`/`faixa_preco_fator_bp`/`tabela_preco_id` em `Solicitacao`; `Configuração.PRECO_ARREDONDAMENTO_CENTAVOS`. Heurística em `10-motor-precificacao.md`. |
| 2026-08-17 (6ª passada) | B005: `DocumentoProfissional` ganha campos de apólice RC (`SEGURO_RC`, vigência, número); enum `TipoDocumentoProfissional`; sinistro durante execução usa disputa existente, sem entidade própria no MVP. |
| 2026-08-17 (issue #4) | Adiciona entidade `PerfilProfissional` (nível de confiança, métricas cacheadas, enum `NivelConfianca`) e referencia critérios/recálculo em `foundation/05-trust-level.md`. |
| 2026-08-17 | RF002: critérios de verificação documental (`DocumentoProfissional`), matriz base + NR-10 para Elétrica, enums `TipoDocumentoProfissional`/`StatusDocumentoProfissional`, regra de `Conta.status` → `ATIVA` (INV-002). |
| 2026-08-17 | B003: cancelamento Cenário B (captura parcial/multa), `PaymentDispute.tipo` + campos de resolução, parâmetros `CANCELLATION_PENALTY_*` e `DISPUTE_MEDIATION_DAYS` em `Configuração`. |
