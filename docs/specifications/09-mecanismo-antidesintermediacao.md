# 09 — Mecanismo Antidesintermediação

**Status:** Proposta inicial (2026-08-17) — **não é decisão fechada**. Precisa de validação de Produto (agressividade do bloqueio vs. fricção do usuário) e Jurídico (monitoramento de conteúdo de chat entre particulares tem implicação de privacidade/LGPD que não foi avaliada aqui).

## Contexto

`01-visao-geral.md` (OBJ-NEG-03) chama a desintermediação ("fechar por fora") de **risco central do modelo, não um detalhe**. Até esta versão, nenhum RF/RN/INV tratava disso e a referência a "RNF de retenção em `07-engenharia.md`" estava quebrada — esse RNF nunca existiu. Este documento é a primeira tentativa de fechar esse gap.

**Premissa que este documento assume e que precisa ser validada:** sem retenção real de dinheiro em escrow (`ADR-002-financeiro.md` rejeitou escrow bancário), o único custo de sair da plataforma depois de combinar o serviço é perder a garantia digital — e a garantia hoje está bloqueada em B001. Ou seja, **este mecanismo sozinho não resolve o risco**; ele reduz a oportunidade de vazamento de contato, mas não cria o incentivo financeiro de ficar. B001 (garantia) e a percepção de valor do Prontuário do Imóvel (OBJ-NEG-02) carregam boa parte do peso real de retenção.

## 1. Mascaramento de contato no chat

Toda `Mensagem` passa por um filtro antes de ser persistida e entregue:

- **Regex de telefone**: padrões BR (com/sem DDD, com separadores, por extenso — "zap", "whats", números escritos por extenso ou com espaçamento anômalo tipo "9 9 8 8 7...").
- **Regex de e-mail**: padrão padrão + variações com "arroba", "ponto com" por extenso.
- **Handles de redes sociais**: `@usuario`, menções a Instagram/Telegram/WhatsApp por nome.
- **Ação**: trecho identificado é substituído por `[contato removido]` na mensagem entregue; o texto original fica retido apenas para fins de auditoria interna (não visível ao destinatário).

Falsos positivos são esperados (números de orçamento, medidas, CEP). Ajuste de sensibilidade é trabalho de Produto pós-lançamento, não algo a travar o MVP.

## 2. Detecção de tentativa (evento, não bloqueio silencioso)

Toda vez que o filtro age, gera um evento `ContactLeakAttempt`:

**Campos**: id, servico_id, usuario_id (quem enviou), mensagem_id, padrao_detectado (`TELEFONE | EMAIL | REDE_SOCIAL`), criado_em

Isso é o dado bruto por trás da métrica de vazamento (seção 4) e da régua de penalidade (seção 3). Não é silencioso: o remetente vê um aviso in-app explicando por que o trecho foi removido (transparência > pega-ratão, para não gerar desconfiança na plataforma).

## 3. Régua de penalidade (gradual, sempre auditável)

| Ocorrência | Ação |
|---|---|
| 1ª–2ª tentativa (rolling 90 dias) | Aviso in-app, sem registro visível ao outro lado. |
| 3ª–4ª tentativa | Aviso + nota interna no perfil (visível a Admin, não ao cliente/profissional). |
| 5ª+ tentativa | Revisão manual por Admin — pode gerar suspensão temporária (`Conta.status = SUSPENSA`, INV-003). |

Toda transição de penalidade é evento de `Auditoria` (INV-070). **Não existe banimento automático** — a régua acima é só gatilho para revisão humana a partir da 5ª ocorrência; decisão final de suspensão/bloqueio é sempre ação de Admin auditada. Prazo de reset da contagem (90 dias) é arbitrário nesta proposta e precisa validação de Produto.

## 4. Métrica de vazamento (visibilidade para negócio)

- **Taxa de tentativa**: `ContactLeakAttempt` únicas / total de `Serviço` no período.
- **Taxa de conclusão pós-tentativa**: entre serviços com ao menos uma tentativa detectada, qual fração chega a `APROVADO` pela plataforma vs. é cancelada/abandonada logo depois — proxy para "fechou por fora e sumiu".
- Exposto em `GET /admin/dashboard` (`06-apis.md`) como indicador, não como alarme binário.

## 5. Incentivo positivo (complementar ao bloqueio)

Bloquear sem oferecer motivo para ficar tende a só irritar o usuário. Ao detectar uma tentativa, a mensagem de aviso in-app deve reforçar o que se perde saindo da plataforma: garantia registrada, histórico no prontuário do imóvel, mediação em caso de disputa. Este texto depende de B001 estar resolvido para não prometer algo que a plataforma ainda não decidiu garantir.

## Pendências para Validação

- Sensibilidade do filtro (falsos positivos vs. falsos negativos) — Produto, com dados reais pós-lançamento.
- Prazo de reset da régua de penalidade (90 dias é chute inicial).
- Implicação de LGPD/privacidade de reter o texto original de mensagens filtradas para auditoria — Jurídico.
- Este mecanismo cobre texto; não cobre trocar contato por foto/áudio. Fora de escopo do MVP (aceitar o risco residual ou reavaliar OCR/transcrição é decisão de V1+).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 | Criação — fecha a referência quebrada de OBJ-NEG-03 em `01-visao-geral.md`. Proposta inicial, não decisão validada. |
