# 12: Próximos Passos de Execução

> Criado em 2026-08-22. `08-planejamento.md` diz o que o MVP precisa ter e `11-epicos-frontend.md` decompõe as telas; nenhum dos dois diz **em que ordem atacar agora**, com o código que já existe. Este documento é o fio condutor de execução: o que está pronto, o que falta e a sequência escolhida. Curto por design: quando um item sai, ele some daqui, não vira histórico.

## Onde o projeto está (2026-08-22)

| Superfície | Estado |
|---|---|
| API (`resolve-plus`) | Domínio do MVP implementado ponta a ponta, suíte verde. É a parte madura. |
| App Flutter (`resolve-plus-app`) | F1-F9 no ar em Stage: auth, documentos, imóveis, solicitação, propostas, execução do serviço, garantia, avaliação e prontuário. |
| Painel Admin (`resolve-plus-admin`) | Dashboard, categorias, tabelas de preço, usuários, serviços, pagamentos, documentos. No ar em `admin.resolveplus.staging.bigworks.com.br`. |

A jornada do MVP está inteira visível no app: cadastro, solicitação, propostas, contratação, execução, aprovação com liberação de pagamento, garantia acionável, avaliação, prontuário do imóvel e extrato financeiro (F5, F6, F8 e F9 entregues em 2026-08-22). O que sobra são bordas de fluxo, o painel operável e o dinheiro real.

Abrir cidade nova já não depende de `db:seed`: a tela de tabelas de preço saiu. O que trava o painel agora é backend, não frontend.

## Sequência

### 1. Agenda e evidências de conclusão (P1, resto de F6)

- Reagendar pela tela (`POST/PUT /schedule`): hoje a agenda é só leitura no detalhe do serviço.
- Fotos na conclusão: `POST /services/{id}/finish` aceita `photos`, mas não existe endpoint de upload para serviço (o de solicitação é outro), então a tela só manda o relato escrito.

### 2. Painel Admin: o que falta (P1)

- Disputas: **bloqueado por backend**, falta listagem admin (só existe `PUT /disputes/{id}/resolve`).
- Liberação manual de pagamento (`POST /payments/{id}/release`): INV-041 pede justificativa e tratamento de exceção administrativa na tela.

### 3. Pagamento com cartão de ponta a ponta (P1)

- O aceite com cartão exige `credit_card_token`; o app não tokeniza e a tela mostra a opção desabilitada. Decidir onde a tokenização acontece (SDK no cliente ou endpoint próprio na API) antes de prometer cartão a alguém.
- A Stage roda com `PAYMENT_GATEWAY=fake`: o caminho real do Asaas nunca foi exercitado fora dos testes.

### 4. Lacunas de backend que travam frontend

| Lacuna | Trava |
|---|---|
| `GET /notifications`, `PUT /notifications/{id}/read` | RF011, profissional não fica sabendo de solicitação nova |
| Listagem admin de disputas | F10, tela hoje é placeholder |
| `GET /users/{id}` (perfil público) | Ver o profissional por inteiro antes de contratar; o resumo de reputação já vai na proposta (RN026) |
| `POST /proposals/{id}/reject` | Recusa explícita pelo cliente (hoje só o aceite recusa as outras) |
| Registro `MANUAL` no prontuário | Prontuário só cresce por serviço aprovado; entrada manual depende de B004 fechar |
| RF010 proximidade geográfica | Feed de oportunidades filtra só por categoria, sem distância |

### 5. Infra e produção

- Object storage real: upload de foto caiu para disco local por falta de pacote/credencial S3.
- Asaas: sair do sandbox (conta, MCC, webhook de produção) antes de qualquer piloto com dinheiro real.

### Fora da fila técnica (Produto/Jurídico)

Pareceres definitivos de B001 e B005, validação de B004, identidade visual/protótipo (bloqueador aberto desde 2026-08-16), termos de uso e política de privacidade.

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
