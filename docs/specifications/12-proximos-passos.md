# 12: Próximos Passos de Execução

> Criado em 2026-08-22. `08-planejamento.md` diz o que o MVP precisa ter e `11-epicos-frontend.md` decompõe as telas; nenhum dos dois diz **em que ordem atacar agora**, com o código que já existe. Este documento é o fio condutor de execução: o que está pronto, o que falta e a sequência escolhida. Curto por design: quando um item sai, ele some daqui, não vira histórico.

## Onde o projeto está (2026-08-22)

| Superfície | Estado |
|---|---|
| API (`resolve-plus`) | Domínio do MVP implementado ponta a ponta, suíte verde. É a parte madura. |
| App Flutter (`resolve-plus-app`) | F1-F6 no ar em Stage: auth, documentos, imóveis, solicitação, propostas e execução do serviço (iniciar, concluir, aprovar, contestar, cancelar, chat). |
| Painel Admin (`resolve-plus-admin`) | Dashboard, categorias, usuários, serviços, pagamentos, documentos. No ar em `admin.resolveplus.staging.bigworks.com.br`. |

A jornada vai do cadastro até a aprovação do serviço com liberação de pagamento (F5 e F6 entregues em 2026-08-22). O que falta na interface é a volta: garantia, avaliação e prontuário.

## Sequência

### 1. Pagamentos, garantia e avaliação (P0)

- **App F8**: histórico de pagamento por serviço, extrato de eventos, acionar garantia com evidências, avaliar o profissional depois da aprovação.
- Hoje o serviço aprovado emite garantia e entra no prontuário, mas o cliente não vê nem uma coisa nem outra pela tela.
- **App F9** (prontuário) vem junto na sequência: `GET /properties/{id}/history` já existe; o pedaço de registro `MANUAL` continua barrado por B004.

### 2. Agenda e evidências de conclusão (P1, resto de F6)

- Reagendar pela tela (`POST/PUT /schedule`): hoje a agenda é só leitura no detalhe do serviço.
- Fotos na conclusão: `POST /services/{id}/finish` aceita `photos`, mas não existe endpoint de upload para serviço (o de solicitação é outro), então a tela só manda o relato escrito.

### 3. Painel Admin operável (P0)

- Tela de tabelas de preço (`/admin/price-tables` já existe na API): sem ela, ninguém configura preço de cidade nova sem `db:seed`.
- Disputas: **bloqueado por backend**, falta listagem admin (só existe `PUT /disputes/{id}/resolve`).

### 4. Pagamento com cartão de ponta a ponta (P1)

- O aceite com cartão exige `credit_card_token`; o app não tokeniza e a tela mostra a opção desabilitada. Decidir onde a tokenização acontece (SDK no cliente ou endpoint próprio na API) antes de prometer cartão a alguém.
- A Stage roda com `PAYMENT_GATEWAY=fake`: o caminho real do Asaas nunca foi exercitado fora dos testes.

### 5. Lacunas de backend que travam frontend

| Lacuna | Trava |
|---|---|
| `GET /notifications`, `PUT /notifications/{id}/read` | RF011, profissional não fica sabendo de solicitação nova |
| Listagem admin de disputas | F10, tela hoje é placeholder |
| `GET /users/{id}` (perfil público) | Ver o profissional por inteiro antes de contratar; o resumo de reputação já vai na proposta (RN026) |
| `POST /proposals/{id}/reject` | Recusa explícita pelo cliente (hoje só o aceite recusa as outras) |
| Registro `MANUAL` no prontuário | F9, depende de B004 fechar |
| RF010 proximidade geográfica | Feed de oportunidades filtra só por categoria, sem distância |

### 6. Infra e produção

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
