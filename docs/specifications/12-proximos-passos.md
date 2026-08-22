# 12: Próximos Passos de Execução

> Criado em 2026-08-22. `08-planejamento.md` diz o que o MVP precisa ter e `11-epicos-frontend.md` decompõe as telas; nenhum dos dois diz **em que ordem atacar agora**, com o código que já existe. Este documento é o fio condutor de execução: o que está pronto, o que falta e a sequência escolhida. Curto por design: quando um item sai, ele some daqui, não vira histórico.

## Onde o projeto está (2026-08-22)

| Superfície | Estado |
|---|---|
| API (`resolve-plus`) | Domínio do MVP implementado ponta a ponta, suíte verde. É a parte madura. |
| App Flutter (`resolve-plus-app`) | F1-F5 no ar em Stage: auth, documentos, imóveis, solicitação, propostas (comparar, aceitar, propor). |
| Painel Admin (`resolve-plus-admin`) | Dashboard, categorias, usuários, serviços, pagamentos, documentos. No ar em `admin.resolveplus.staging.bigworks.com.br`. |

A jornada vai do cadastro até a contratação com pagamento autorizado (F5 entregue em 2026-08-22). O que ainda não é alcançável pela interface é o que vem **depois** de contratado: acompanhar, executar, aprovar, pagar, avaliar.

## Sequência

### 1. Execução do serviço (P0)

- **App F6**: lista e detalhe de serviço (`GET /services`, `GET /services/{id}`, entregues em 2026-08-22), agenda, chat, iniciar/concluir/aprovar/contestar.
- Regra que a tela não pode ignorar: 409 de `start` com Pix `PENDENTE` é espera, não erro do profissional (INV-048). Hoje o cliente aceita a proposta e não tem mais nenhuma tela para onde ir.

### 2. Painel Admin operável (P0)

- Tela de tabelas de preço (`/admin/price-tables` já existe na API): sem ela, ninguém configura preço de cidade nova sem `db:seed`.
- Disputas: **bloqueado por backend**, falta listagem admin (só existe `PUT /disputes/{id}/resolve`).

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
| Registro `MANUAL` no prontuário | F9, depende de B004 fechar |
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
