# 03: Regras de Cancelamento (rascunho, B003)

> Status: **Em elaboração**. Desbloqueia a modelagem de estados (`02-state-machine.md`) e a estrutura das APIs de cancelamento, mas **não desbloqueia o fluxo principal de execução**: percentual de multa (Cenário B) e o mecanismo de resolução de disputa (Cenário C, mesma pendência de `Em Contestação → ?` já registrada) continuam sem resposta determinística. Registrado pelo PO em 2026-08-17, a partir do rascunho de `04-decisions-pending.md` (B003).

## Os quatro cenários

| Cenário | Momento | Quem pode cancelar | Consequência | Reembolso | Estado seguinte |
|---|---|---|---|---|---|
| A | Antes de proposta aceita (Solicitação `Aberta`/`Recebendo Propostas`) | Cliente | Cancela livremente | N/A, nenhum pagamento foi autorizado ainda (autorização só ocorre quando a proposta é aceita, `adr/ADR-002-financeiro.md`) | Solicitação `Cancelada` |
| B | Após proposta aceita, antes de iniciar (Serviço `Agendado`) | Cliente | Pode gerar multa (percentual **NECESSITA VALIDAÇÃO**) | Parcial (mecânica **NECESSITA VALIDAÇÃO**, ver nota abaixo) | Serviço `Cancelado` |
| C | Durante execução (Serviço `Em Andamento`) | Cliente/Profissional | Nunca cancela diretamente, abre disputa | Depende da resolução da disputa (**NECESSITA VALIDAÇÃO**, mesma pendência de `Em Contestação → ?`) | Serviço `Em Contestação` |
| D | Após conclusão (Serviço `Aprovado`) | Ninguém | Não existe cancelamento, existe contestação/garantia | Conforme mediação | Ver nota "Cenário D" abaixo |

## Mapeamento para o domínio já modelado

**Cenário A** já está coberto por `02-state-machine.md` §1 (Solicitação: `Aberta|Recebendo Propostas --(cliente: cancela)--> Cancelada`, FA002). Trivial financeiramente: pagamento só é autorizado quando a proposta é aceita (`Contratada`, que dispara criação do `Serviço`), então cancelar antes disso não tem nada para estornar.

**Cenário B** decidido no nível de estado (`02-state-machine.md` §3, `Agendado --(cliente: cancela)--> Cancelado`), mas dois pontos ficam abertos:
- **Percentual de multa**: sem preferência provisória registrada.
- **Mecânica de reembolso parcial**: no **cartão**, neste ponto o pagamento está só `AUTORIZADO`, não `CAPTURADO`. `PaymentRefund` (INV-043) só existe sobre valor capturado. "Reembolso parcial" aqui não é um `PaymentRefund`, é uma **captura parcial da autorização** (valor da multa) seguida de cancelamento do restante, ou cancelamento total da autorização com cobrança da multa por evento separado. Captura parcial **não** é assumida no Asaas (`adr/ADR-005-gateway-pagamento.md`); permanece pendência de B003. No **Pix**, a autorização já nasceu `CAPTURADO`: multa/cancelamento no Cenário B é `PaymentRefund` (INV-043) sobre valor capturado, não captura parcial. Não modelar o percentual nem o caminho de cartão em `04-modelo-dados.md` até B003 fechar.

**Cenário C** decidido como regra de fluxo (`02-state-machine.md` §3, `Em Andamento --(cliente/profissional: solicita cancelamento)--> Em Contestação`): uma vez que o profissional iniciou a execução, cancelamento unilateral não existe mais, qualquer pedido de cancelamento vira disputa. A resolução de `Em Contestação → Aprovado | Cancelado` continua **bloqueada** (mesma pendência já registrada em `02-state-machine.md`, "NECESSITA VALIDAÇÃO"), então o reembolso desse cenário não pode ser modelado ainda, depende de como a disputa se resolve.

**Cenário D**, "após conclusão", mapeia para dois mecanismos diferentes dependendo do momento exato, o rascunho do PO tratou como um só, mas o domínio já distingue:
- **Dentro da janela de aceite automático** (Serviço em `Aguardando Aprovação`, ainda não chegou a `Aprovado`): já existe `Aguardando Aprovação --(cliente: contesta)--> Em Contestação` (FA004), é contestação de conclusão, não cancelamento. Mesma pendência de resolução do Cenário C.
- **Depois de `Aprovado`** (estado terminal positivo do Serviço, dispara captura de pagamento e `Garantia`, INV-041/INV-050): não existe mais contestação de serviço, o que existe é acionamento de **Garantia** (`02-state-machine.md` §5, `Ativa --(cliente: aciona com evidências)--> Acionada`). Responsabilidade financeira é do profissional, plataforma media (INV-053, decisão provisória de B001, `adr/ADR-003-garantia.md`), sem relação com o mecanismo de cancelamento deste documento.

## O que fica pendente (não decidir aqui)

- Percentual de multa do Cenário B.
- Mecânica exata de captura parcial (cartão) / reembolso parcial (Pix já capturado) do Cenário B. Gateway é Asaas (`adr/ADR-005-gateway-pagamento.md`); captura parcial continua não assumida.
- Resolução determinística de `Em Contestação → Aprovado | Cancelado` (Cenários C e D-dentro-da-janela), inclui quem decide, prazo de mediação, critério.
- Impacto de cancelamento/contestação recorrente na reputação do profissional/cliente (RN008 já cita isso genericamente, sem regra concreta).

## Responsável

Produto + Jurídico (Cenário B, multa, pode ter implicação de direito do consumidor) + Produto (Cenário C/D, fluxo de mediação).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 | Criado a partir do rascunho do PO sobre B003 (`04-decisions-pending.md`). Mapeia os 4 cenários para os estados já existentes em `02-state-machine.md`, registra Cenário A como já coberto, Cenário B/C como decisão parcial de fluxo (sem valores fechados), Cenário D como dois mecanismos distintos (contestação de conclusão vs. garantia), não um só. |
| 2026-08-17 | Cenário B: distingue cartão (ainda `AUTORIZADO`) de Pix (já `CAPTURADO`, `adr/ADR-005-gateway-pagamento.md`). Captura parcial continua pendência de B003. |
