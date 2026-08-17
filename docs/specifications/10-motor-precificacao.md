# 10: Motor de Precificação (Heurística MVP)

> Fecha OBJ-MVP-01 e OBJ-TEC-02 (`01-visao-geral.md`): o cliente recebe uma **faixa de preço estimada** antes de contratar. Fora do escopo do MVP: motor de pricing orientado a dados/ML (`01-visao-geral.md` §11). Este documento é a especificação de implementação; o modelo está em `04-modelo-dados.md` e a API em `06-apis.md`.

**Status:** Decisão de produto para o MVP (2026-08-17). A faixa é informativa, não é proposta e não é preço fechado. O valor cobrável continua sendo `Proposta.valor` da proposta aceita.

## 1. Pesquisa: cold start sem histórico próprio

A cidade piloto ainda não tem serviços `APROVADO` o bastante para derivar percentis. Marketplaces de serviço resolvem esse bootstrap de três jeitos; o padrão dominante no lançamento é **tabela de referência por categoria/região, editada na mão**, depois substituída por dado real.

| Plataforma | O que o cliente vê antes de contratar | Como nasce o número sem histórico local | Evolução com volume |
|---|---|---|---|
| GetNinjas | Não promete faixa da plataforma. Cliente pede orçamento e recebe até 4 propostas de profissionais. Precificação do *pedido* (custo em moedas para o profissional desbloquear o lead) varia por tipo de serviço, demanda da região, oferta de profissionais e distância ([central de ajuda](https://www.getninjas.com.br/central-de-ajuda/profissional/pedidos-sou-profissional/como-funciona-a-precificacao-dos-pedidos)). | Não há estimativa ao cliente no fluxo principal; o "preço" visível é o das propostas. | Continua sendo marketplace de leads: o histórico alimenta precificação do lead, não uma faixa ao cliente. |
| Habitissimo / ProFinder | Guias públicos "quanto custa" com faixa por tipo de obra e cidade, rotulados como estimativa. Ex.: tabelas por cidade em [habitissimo.pt/orcamentos](https://www.habitissimo.pt/orcamentos/remodelacoes). O contrato só existe depois do orçamento do profissional. | Seed editorial: valores "provêm de informações reais dos utilizadores, contrastadas e revistas por especialistas" ([guia de serviços](https://www.habitissimo.pt/orcamentos/servicos)). No dia 1, é tabela curada, não modelo estatístico. | Agregam orçamentos recebidos e revisão humana; não substituem a proposta. |
| TaskRabbit | Cliente vê faixa/hora e custo estimado por categoria e metro (zip). Tasker recebe *pricing guidance* (taxa em que clientes mais contratam) por metro, categoria e experiência ([blog](https://www.taskrabbit.com/blog/how-pricing-guidance-works-on-taskrabbit/)). Cost Guides mesclam jobs concluídos e pesquisa de mercado ([cost-guides](https://www.taskrabbit.com/cost-guides)). | Cidade nova: guidance e cost guide partem de metros comparáveis + pesquisa, não de ML local. Tasker ainda define o próprio preço. | Com volume, a guidance passa a refletir dados de contratação daquele metro. |
| Thumbtack | Homeowner responde perguntas de escopo (tipo, prazo, faixa de orçamento, zip) e vê profissionais / Instant Match. A plataforma publica *price guides* por projeto e local; o preço cobrável é o do profissional. | Guias nacionais/regionais e perguntas de qualificação. Instant Match usa preços que o profissional cadastrou, não um modelo da plataforma. | Guias passam a incorporar jobs concluídos por zip/categoria. |
| iFood-like (tarifa de marketplace) | No delivery o cardápio é do lojista (não análogo). Na tarifa da plataforma (entrega, corrida): tabela operacional (base + deslocamento), não ML. | Ops publica a tabela antes do primeiro pedido. | Ajuste manual e, depois, elasticidade com volume. Serviço residencial se parece mais com isso do que com cardápio. |

**Síntese para o Resolve+:** GetNinjas *não* entrega OBJ-MVP-01 (não há faixa antes de falar com profissional). Habitissimo, TaskRabbit e Thumbtack entregam faixa **não vinculante** nascida de tabela/guia, não de modelo. Marketplaces tipo iFood resolvem o dia 1 com tabela operacional. O MVP copia esse padrão: `TabelaPreco` por categoria + cidade, editável por Admin, com ajuste determinístico pelo `template_escopo`. Histórico real só entra depois de volume (seção 5), nunca como ML no MVP.

## 2. Heurística MVP

Três entradas, alinhadas a OBJ-TEC-02:

1. **Categoria:** `Solicitacao.categoria_id` → linha em `TabelaPreco`.
2. **Região:** `Property.cidade` da `property_id` da solicitação. MVP é cidade única (`01-visao-geral.md` OBJ-MVP-06); a chave já é cidade para não redesenhar na primeira expansão.
3. **Histórico:** no lançamento, a própria `TabelaPreco` *é* o histórico bootstrap (curada pelo Admin). Serviços `APROVADO` não entram na fórmula até a seção 5.

IA/ML não participa. Não há chamada a modelo, não há peso aprendido, não há "orçamento assistido por IA" neste fluxo (o resumo executivo de `01-visao-geral.md` que cita IA no orçamento permanece visão de produto; a implementação do MVP é esta heurística).

### 2.1 Tabela base

`TabelaPreco` (ver `04-modelo-dados.md`): uma linha ativa por `(categoria_id, cidade)`.

| Campo | Semântica |
|---|---|
| `valor_min` / `valor_max` | Inteiros em centavos. `valor_min > 0` e `valor_max >= valor_min`. |
| `ativo` | Linha inativa não é usada. Sem linha ativa para o par categoria+cidade, a estimativa **falha** (não inventa número). |

Valores iniciais são **chutes operacionais** do Admin antes do lançamento, não verdade de mercado. Exemplos ilustrativos (substituir na cidade piloto; não copiar para produção sem revisão):

| Categoria (MVP) | `valor_min` | `valor_max` | Faixa em R$ |
|---|---|---|---|
| Elétrica | 8000 | 25000 | 80-250 |
| Hidráulica | 10000 | 35000 | 100-350 |
| Pintura | 30000 | 150000 | 300-1500 |
| Pequenos reparos | 6000 | 20000 | 60-200 |
| Montagem | 8000 | 30000 | 80-300 |

### 2.2 Ajuste por `template_escopo`

`Categoria.template_escopo` já descreve os campos do `Solicitacao.escopo` (INV-080). Campos **podem** declarar `ajuste_preco` opcional. Campo sem `ajuste_preco` não altera a faixa.

Todos os fatores usam **basis points inteiros**: `10000` = 1.0. Nada de `FLOAT`/`DECIMAL` monetário (convenção de `04-modelo-dados.md`).

**Tipo `enum`:** `fator_campo_bp = mapa[valor_do_escopo]`, default `10000` se a chave não existir.

**Tipo `linear`:** `fator_campo_bp = clamp(10000 + por_unidade_bp * max(0, valor - base), fator_min_bp, fator_max_bp)`.

**Tipo `bool`:** `fator_campo_bp = se_true_bp` quando verdadeiro, senão `10000`.

Exemplo (Pintura, ilustrativo):

```json
{
  "comodos": {
    "tipo": "int",
    "obrigatorio": true,
    "ajuste_preco": {
      "tipo": "linear",
      "base": 1,
      "por_unidade_bp": 1500,
      "fator_min_bp": 10000,
      "fator_max_bp": 25000
    }
  },
  "area_m2": {
    "tipo": "number",
    "obrigatorio": true,
    "ajuste_preco": {
      "tipo": "linear",
      "base": 20,
      "por_unidade_bp": 200,
      "fator_min_bp": 10000,
      "fator_max_bp": 30000
    }
  },
  "tipo_tinta": {
    "tipo": "enum",
    "obrigatorio": true,
    "valores": ["latex", "acrilica", "epoxi"],
    "ajuste_preco": {
      "tipo": "enum",
      "mapa": { "latex": 10000, "acrilica": 11000, "epoxi": 13000 }
    }
  }
}
```

### 2.3 Fórmula (aritmética inteira)

```
tabela = TabelaPreco ativa onde categoria_id e cidade = Property.cidade
se ausente → erro PRECO_TABELA_AUSENTE (não calcula)

fator_bp = 10000
para cada campo do template com ajuste_preco:
  fator_bp = fator_bp * fator_campo_bp / 10000   # divisão inteira

min = arredondar(tabela.valor_min * fator_bp / 10000)
max = arredondar(tabela.valor_max * fator_bp / 10000)

arredondar(x) = x arredondado para o múltiplo de PRECO_ARREDONDAMENTO_CENTAVOS
                (Configuração, default 1000 = R$ 10)

se min < 1 → min = PRECO_ARREDONDAMENTO_CENTAVOS
se max < min → max = min
se max == min → max = min + PRECO_ARREDONDAMENTO_CENTAVOS
```

A faixa resultante é sempre intervalo (`min < max` depois do último passo), nunca um ponto único, para não parecer preço fechado.

### 2.4 Snapshot na Solicitacao

No instante do cálculo, persistir em `Solicitacao`:

- `faixa_preco_min`, `faixa_preco_max` (centavos)
- `faixa_preco_fator_bp`
- `tabela_preco_id` (linha usada)

Editar `TabelaPreco` depois **não** reescreve solicitações já criadas. A faixa que o cliente viu permanece. Recálculo só ocorre em `PUT /requests/{id}` quando `scope`/`category_id`/`property_id` mudam **e** ainda não há proposta (mesmo recorte de INV-080); se já houver proposta, o snapshot fica congelado junto com o escopo. Cancelar e recriar também gera faixa nova.

A faixa **não** é input do cliente nem do profissional. `POST /requests` ignora qualquer `faixa_preco_estimada` no body.

## 3. Onde aparece (API)

Detalhe de payload em `06-apis.md`. RF029. Declarar `POST /requests/estimate` antes de `/requests/{id}`.

| Campo no modelo (`04-modelo-dados.md`) | Campo na API |
|---|---|
| `faixa_preco_min` | `estimated_price_min` |
| `faixa_preco_max` | `estimated_price_max` |
| `faixa_preco_fator_bp` | `estimated_price_factor_bp` |
| `tabela_preco_id` | `price_table_id` |

Valores monetários em centavos (inteiro).

| Endpoint | Papel |
|---|---|
| `POST /requests/estimate` | Pré-visualização: mesmo body de criação, **não persiste**. Devolve a faixa para o app mostrar antes do commit. Cobre "antes de contratar" / "antes de falar com profissional" (`01-visao-geral.md` §3 e OBJ-MVP-01). |
| `POST /requests` | Cria a solicitação, grava o snapshot, devolve a faixa na resposta `201`. |
| `GET /requests/{id}` | Inclui o snapshot. |
| `GET /admin/price-tables` | Lista a tabela. |
| `POST /admin/price-tables` | Cria linha (Admin). |
| `PUT /admin/price-tables/{id}` | Edita `valor_min` / `valor_max` / `ativo` (Admin). |

Erro `422` com código `PRECO_TABELA_AUSENTE` quando não há linha ativa para categoria + `Property.cidade`. Lançamento: Admin semeia as 5 categorias da cidade piloto **antes** de abrir `POST /requests` ao cliente. Não há fallback silencioso.

## 4. O que a faixa não é

- Não é `Proposta`. Profissional continua livre para orçar acima ou abaixo.
- Não é valor autorizado no pagamento. `PaymentAuthorization.valor` nasce da proposta aceita (`adr/ADR-002-financeiro.md`).
- Não é teto nem piso de proposta no MVP (não rejeitar `POST /requests/{id}/proposals` por sair da faixa). Se Produto quiser alerta de "muito fora da faixa" depois, é regra nova, não está aqui.
- Não substitui comparabilidade de propostas: isso continua sendo INV-080 (`escopo` compartilhado).

Texto de UI obrigatório junto da faixa: estimativa da plataforma, não orçamento do profissional, valor final é o da proposta aceita.

## 5. Evolução (fora do cálculo automático do MVP)

OBJ-TEC-02 fala em histórico de serviços. Caminho, **manual**, depois do volume:

- Critério sugerido (não job automático no MVP): `N >= 30` serviços `APROVADO` no par `(categoria_id, cidade)`.
- Admin pode substituir `valor_min`/`valor_max` pelos percentis 25 e 75 de `Proposta.valor` das propostas `ACEITA` desses serviços.
- Solicitações antigas não mudam (snapshot).

ML, elasticidade, preço por bairro/CEP e "IA para estimativa" permanecem pós-MVP (`05-arquitetura.md`, `08-planejamento.md` V2).

## 6. Pendências

- Valores reais da `TabelaPreco` na cidade piloto (os exemplos da seção 2.1 são chutes).
- Cidade piloto em si (ainda aberta em `08-planejamento.md`).
- Quais campos de cada `template_escopo` carregam `ajuste_preco` no lançamento (Admin/Produto; a fórmula já está fechada).
- Se, pós-MVP, proposta fora da faixa deve ser bloqueada ou só sinalizada.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 | Criação. Pesquisa de cold start, heurística de tabela + fator de escopo, snapshot em Solicitacao, preview e retorno em `POST /requests`. |
| 2026-08-17 | Mapeia campos do modelo para a API (`estimated_price_*`) e referencia RF029. |
