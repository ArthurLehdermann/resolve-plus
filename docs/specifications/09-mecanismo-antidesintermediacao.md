# 09, Mecanismo Antidesintermediação

**Status:** Proposta inicial (2026-08-17, revisada na mesma data após 2ª crítica do PO), **não é decisão fechada**. Precisa de validação de Produto (agressividade do bloqueio vs. fricção do usuário) e Jurídico (monitoramento de conteúdo de mensagens entre particulares tem implicação de privacidade/LGPD que não foi avaliada aqui).

**Limite fundamental deste documento, dito sem rodeio:** ele cobre só o canal de texto assíncrono dentro da plataforma. O canal dominante para desintermediação num marketplace de serviço presencial é a **visita presencial**, o profissional já entra na casa do cliente antes/durante a execução, e nenhum filtro de texto alcança isso. Este mecanismo reduz vazamento no canal que dá pra instrumentar; não resolve o risco central sozinho. A alavanca real de retenção é financeira (garantia, B001, e percepção de valor do Prontuário) e ela está travada.

## Contexto

`01-visao-geral.md` (OBJ-NEG-03) chama a desintermediação ("fechar por fora") de **risco central do modelo, não um detalhe**. Até esta versão, nenhum RF/RN/INV tratava disso e a referência a "RNF de retenção em `07-engenharia.md`" estava quebrada, esse RNF nunca existiu. Este documento é a primeira tentativa de fechar esse gap.

**Premissa que este documento assume e que precisa ser validada:** sem retenção real de dinheiro em escrow (`ADR-002-financeiro.md` rejeitou escrow bancário), o único custo de sair da plataforma depois de combinar o serviço é perder a garantia digital, e a garantia hoje está bloqueada em B001. Ou seja, **este mecanismo sozinho não resolve o risco**; ele reduz a oportunidade de vazamento de contato, mas não cria o incentivo financeiro de ficar. B001 (garantia) e a percepção de valor do Prontuário do Imóvel (OBJ-NEG-02) carregam boa parte do peso real de retenção.

## 1. Mascaramento de contato, dois canais, não um

> Corrigido em 2026-08-17: a versão anterior só cobria `Mensagem` (chat), que só existe **depois** do aceite da proposta (`servico_id`). Nesse ponto o vazamento já foi contido pelo próprio fluxo, cliente e profissional já combinaram o serviço pela plataforma. O canal mais óbvio é anterior: `Proposta.observacoes`, texto livre visível ao cliente **antes** do aceite, sem filtro algum, e é o único canal que o profissional controla antes de haver comissão em jogo.

O filtro abaixo se aplica a **dois campos**, não um: `Proposta.observacoes` (no envio da proposta) e `Mensagem.texto` (no chat pós-aceite). Mesma lógica, dois pontos de aplicação.

- **Regex de telefone**: padrões BR (com/sem DDD, com separadores, por extenso, "zap", "whats", números escritos por extenso ou com espaçamento anômalo tipo "9 9 8 8 7...").
- **Regex de e-mail**: padrão padrão + variações com "arroba", "ponto com" por extenso.
- **Handles de redes sociais**: `@usuario`, menções a Instagram/Telegram/WhatsApp por nome.
- **Ação**: trecho identificado é substituído por `[contato removido]` na mensagem entregue; o texto original fica retido apenas para fins de auditoria interna (não visível ao destinatário).

Falsos positivos são esperados (números de orçamento, medidas, CEP). Ajuste de sensibilidade é trabalho de Produto pós-lançamento, não algo a travar o MVP.

## 2. Detecção de tentativa (evento, não bloqueio silencioso)

Toda vez que o filtro age, gera um evento `ContactLeakAttempt`:

**Campos**: id, usuario_id (quem enviou), origem (`PROPOSTA | MENSAGEM`), proposta_id (preenchido se `origem = PROPOSTA`), servico_id (preenchido se `origem = MENSAGEM`), padrao_detectado (`TELEFONE | EMAIL | REDE_SOCIAL`), criado_em

`proposta_id`/`servico_id` são mutuamente exclusivos porque `Proposta.observacoes` existe antes de haver `Serviço`, não dá pra forçar os dois campos preenchidos.

Isso é o dado bruto por trás da métrica de vazamento (seção 4) e da régua de penalidade (seção 3). Não é silencioso: o remetente vê um aviso in-app explicando por que o trecho foi removido (transparência > pega-ratão, para não gerar desconfiança na plataforma).

## 3. Régua de penalidade (automática, sem depender de time de moderação)

> Corrigido em 2026-08-17: a versão anterior terminava em "revisão manual por Admin" como se existisse um processo de moderação. Não existe, MVP é operado por dev solo. Uma régua que depende de alguém revisar caso a caso não vai rodar. Reescrita para ser 100% automática, com o Admin (hoje, o próprio PO) entrando só depois, para reverter se a régua errou, não para decidir a cada ocorrência.

| Ocorrência | Ação (automática) |
|---|---|
| 1ª–2ª tentativa (rolling 90 dias) | Aviso in-app, sem registro visível ao outro lado. |
| 3ª–4ª tentativa | Aviso + nota interna no perfil (visível em `GET /admin/dashboard`, não ao cliente/profissional). |
| 5ª+ tentativa | `Conta.status = SUSPENSA` automático (INV-003), com evento de `Auditoria` (INV-070) e notificação ao Admin. Reversível, Admin pode reativar se for falso positivo. |

Prazo de reset da contagem (90 dias) e o limiar de 5 ocorrências são chutes iniciais desta proposta, precisam de validação de Produto e, idealmente, de dados reais pós-lançamento antes de travar como regra definitiva.

## 4. Métrica de vazamento (visibilidade para negócio)

- **Taxa de tentativa (pré-aceite)**: `ContactLeakAttempt` com `origem = PROPOSTA` únicas / total de `Proposta` no período.
- **Taxa de tentativa (pós-aceite)**: `ContactLeakAttempt` com `origem = MENSAGEM` únicas / total de `Serviço` no período.
- **Taxa de conclusão pós-tentativa**: entre serviços com ao menos uma tentativa detectada (qualquer origem), qual fração chega a `APROVADO` pela plataforma vs. é cancelada/abandonada logo depois, proxy para "fechou por fora e sumiu".
- Exposto em `GET /admin/dashboard` (`06-apis.md`) como indicador, não como alarme binário.

## 5. Incentivo positivo (complementar ao bloqueio)

Bloquear sem oferecer motivo para ficar tende a só irritar o usuário. Ao detectar uma tentativa, a mensagem de aviso in-app deve reforçar o que se perde saindo da plataforma: garantia registrada, histórico no prontuário do imóvel, mediação em caso de disputa. Este texto depende de B001 estar resolvido para não prometer algo que a plataforma ainda não decidiu garantir.

## Pendências para Validação

- Sensibilidade do filtro (falsos positivos vs. falsos negativos), Produto, com dados reais pós-lançamento.
- Prazo de reset da régua de penalidade (90 dias é chute inicial).
- Implicação de LGPD/privacidade de reter o texto original de mensagens filtradas para auditoria, Jurídico.
- Este mecanismo cobre texto; não cobre trocar contato por foto/áudio. Fora de escopo do MVP (aceitar o risco residual ou reavaliar OCR/transcrição é decisão de V1+).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 (1ª passada) | Criação, fecha a referência quebrada de OBJ-NEG-03 em `01-visao-geral.md`. Proposta inicial, não decisão validada. |
| 2026-08-17 (2ª passada) | Corrige o canal errado: filtro passa a cobrir `Proposta.observacoes` (pré-aceite) além de `Mensagem` (pós-aceite). Régua de penalidade vira 100% automática, removida a etapa de "revisão manual" que pressupunha um time de moderação inexistente. Adiciona reconhecimento explícito de que o canal presencial não é coberto por este mecanismo. |
