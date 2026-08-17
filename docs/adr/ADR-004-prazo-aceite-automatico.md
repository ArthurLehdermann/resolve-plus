# ADR-004: Prazo para Aceite Automático / Repasse

**Status:** Decisão provisória de produto (não é bloqueador jurídico, ao contrário de B001). Pode ser alterada por configuração.

**Data:** 2026-08-17 (PO)

## Contexto

`foundation/04-decisions-pending.md` (B002) registrava como bloqueador o prazo entre o profissional registrar conclusão do serviço (`Aguardando Aprovação`, `02-state-machine.md` §3) e o aceite automático que dispara captura/repasse (INV-031, INV-041), na ausência de manifestação do cliente. Alternativas avaliadas: 24h, 48h, 72h, ou sem aceite automático.

Diferente de B001 (garantia), esta não é uma decisão que dependa de parecer jurídico, é decisão de produto sobre janela de contestação, o risco de errar o valor é operacional (poucas contestações vs. clientes sem tempo de reagir), não regulatório.

## Decisão

**72 horas.**

```
Serviço concluído (profissional registra)
      ↓
Cliente recebe notificação
      ↓
Janela de 72h (AUTO_APPROVAL_HOURS)
      ↓
Sem manifestação --------> Serviço aprovado automaticamente --> captura + repasse (INV-041)
      ↓
Cliente contesta ---------> Em Contestação (fluxo de mediação, B003)
```

## Modelagem: parâmetro, não valor fixo

Modelado como `AUTO_APPROVAL_HOURS` na tabela `Configuração` (`specifications/04-modelo-dados.md`, "Tabelas Auxiliares"), lido pelo job que dispara o aceite automático. Não é uma constante em código, alterar o prazo (ex.: para 48h após dados reais de uso) é mudança de configuração, não exige migration nem alteração de schema.

## Consequências

- `00-domain-invariants.md` (INV-031, INV-041) deixam de citar o prazo como "Necessita Validação", passam a referenciar `AUTO_APPROVAL_HOURS`.
- `02-state-machine.md` §3 (transição `Aguardando Aprovação → Aprovado` por expiração de janela) e §4b (evento `REPASSADO`) deixam de citar "prazo ainda não definido".
- `specifications/02-funcionalidades.md` RN010 sai de "Necessita Validação".
- Não desbloqueia B003 (mediação de contestação dentro da janela continua dependente de regras de cancelamento/disputa).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-17 | Decisão de produto do PO: 72h, modelado como parâmetro `AUTO_APPROVAL_HOURS`. Resolve B002 (`04-decisions-pending.md`) provisoriamente. |
