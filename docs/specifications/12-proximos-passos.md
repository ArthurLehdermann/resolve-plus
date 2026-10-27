# 12: Próximos Passos de Execução

> Criado em 2026-08-22. `08-planejamento.md` diz o que o MVP precisa ter e `11-epicos-frontend.md` decompõe as telas; nenhum dos dois diz **em que ordem atacar agora**, com o código que já existe. Este documento é o fio condutor de execução: o que está pronto, o que falta e a sequência escolhida. Curto por design: quando um item sai, ele some daqui, não vira histórico.

## Onde o projeto está (2026-10-27)

| Superfície | Estado |
|---|---|
| API (`resolve-plus`) | Domínio do MVP ponta a ponta, suíte verde. Auth sem senha (código por e-mail + Google), Object Storage S3, LGPD (política/termos no site, exportação/exclusão, atendimento no admin), listagem admin de disputas, site público na raiz. |
| App Flutter (`resolve-plus-app`) | F1-F9 no ar na Stage: auth, documentos, imóveis, solicitação, propostas, execução, garantia, avaliação e prontuário. |
| Painel Admin (`resolve-plus-admin`) | App separada (Next.js). Dashboard, categorias, tabelas de preço, usuários, serviços, pagamentos, documentos e disputas com contexto de mediação. No ar na Stage (`admin.` no mesmo domínio da API). |

A jornada do MVP está inteira visível no app. O que sobra são bordas de fluxo, cartão de ponta a ponta e dinheiro real (Asaas fora do gateway fake).

## Sequência

### 1. Evidências de conclusão (P1, resto de F6)

- Fotos na conclusão: `POST /services/{id}/finish` aceita `photos`, mas não há upload dedicado de evidência de serviço (o de solicitação e o de garantia são outros). A tela hoje depende de relato escrito ou de URLs já existentes.

### 2. Painel Admin: o que falta (P1)

- Liberação manual de pagamento (`POST /payments/{id}/release`): INV-041 pede justificativa e tratamento de exceção administrativa na tela.

### 3. Pagamento com cartão de ponta a ponta (P1)

- O aceite com cartão exige `credit_card_token`; o app não tokeniza e a tela mostra a opção desabilitada. Decidir onde a tokenização acontece (SDK no cliente ou endpoint próprio na API) antes de prometer cartão a alguém.
- A Stage roda com `PAYMENT_GATEWAY=fake`: o caminho real do Asaas nunca foi exercitado fora dos testes. Credenciais e webhook ficam no arquivo de secrets do host de deploy (não no repositório).

### 4. Lacunas de backend que travam frontend

| Lacuna | Trava |
|---|---|
| `GET /users/{id}` (perfil público) | Ver o profissional por inteiro antes de contratar; o resumo de reputação já vai na proposta (RN026) |
| `POST /proposals/{id}/reject` | Recusa explícita pelo cliente (hoje só o aceite recusa as outras) |
| Registro `MANUAL` no prontuário | Prontuário só cresce por serviço aprovado; entrada manual depende de B004 fechar |
| RF010 proximidade geográfica | Feed de oportunidades e alcance da notificação de solicitação nova filtram só por categoria, sem distância |

### 5. Infra e produção

- Asaas: sair do sandbox (conta, MCC, webhook de produção) antes de qualquer piloto com dinheiro real.
- CI roda no runner self-hosted da Stage (label no workflow do repositório); minutos hosted do GitHub não são usados.

### Fora da fila técnica (Produto/Jurídico)

Pareceres definitivos de B001 e B005, validação de B004, identidade visual/protótipo (bloqueador aberto desde 2026-08-16). Termos de uso e política de privacidade já estão no site e alinhados ao código (revisão LGPD).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-22 | Criação. Registra a sequência escolhida depois do diagnóstico dos três repos; entrega `GET /services`/`GET /services/{id}` e o isolamento do banco de teste que motivaram o item 2. |
| 2026-08-22 | F5 entregue (app) e painel admin deployado: os dois itens saem da fila. Sobe execução do serviço para primeiro. Cartão vira item próprio, com a pendência de tokenização explicitada. |
| 2026-08-22 | F6 entregue (lista, detalhe, ações de estado e chat). Sobra dele agenda e fotos de conclusão, que viram item P1 separado. Garantia/avaliação/prontuário assumem o topo. |
| 2026-08-22 | F8 entregue (garantia com acionamento por evidência e avaliação). O upload de evidência de garantia foi criado no backend para destravar isso. Prontuário assume o topo. |
| 2026-08-22 | F9 entregue (timeline do prontuário com selo de origem, leitura pelo card do imóvel). Sem entrada manual, que segue barrada por B004. Histórico de pagamento assume o topo. |
| 2026-08-22 | Resto de F8 entregue (histórico paginado e extrato de eventos com split). Fecha o épico. Sobram agenda/fotos de F6 e o painel admin, agora o único P0. |
| 2026-08-22 | Tabelas de preço no painel: abrir cidade nova deixa de exigir `db:seed`. Não sobrou P0 na fila; o que trava o painel agora é backend (listagem de disputas). |
| 2026-08-22 | Notificações (RF011) de ponta a ponta: tabela, `GET /notifications` + marcação de lida, seis listeners nos eventos que já existiam e feed no app com badge. Sem push nem tempo real, que seguem Pós-MVP. O stub que só logava saiu. |
| 2026-10-27 | Revisão contra o código: listagem admin de disputas, Object Storage S3, login código/Google, site público, LGPD (termos/política + direitos do titular) e `POST`/`PUT /schedule` saem da fila ativa. Agenda deixa de ser item P1. Hostnames/VPS fixos generalizados para "a Stage" / secrets do host de deploy. |
