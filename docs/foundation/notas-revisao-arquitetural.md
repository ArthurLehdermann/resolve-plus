# Notas, Revisão Arquitetural (2026-08-16)

Revisão crítica sobre a documentação 01–08, com pontos elevados a bloqueador de arquitetura (não apenas inconsistência editorial).

## Prontidão revista

"88% pronto" está superestimado. Rebaixar para **70–75%**: falta máquina de estados completa, modelo financeiro indefinido, responsabilidade jurídica indefinida, fluxo de cancelamento incompleto, enquanto isso não fecha, o domínio ainda pode mudar.

## Pagamento é o Aggregate Root, não um módulo

`Pagamento` foi documentado como módulo comum, mas na prática é um bounded context inteiro: dele derivam contratação, garantia, disputa, cancelamento, comissão, reembolso, repasse, auditoria. Muda a arquitetura.

## Falta State Machine explícita

Enums (`StatusSolicitacao`, `StatusServico` etc.) não representam regras de transição. Falta documentar, por estado, quem pode transicionar, sob quais condições (multa, estorno, agenda, chat), considerado o maior documento faltante hoje.

## Outras inconsistências de modelagem

- **Contratação vs Serviço**: `Contratação` pode não ter identidade própria, é só "proposta aceita". Avaliar se deveria ser evento em vez de entidade, ou se `Serviço` nasce direto da proposta aceita.
- **Histórico do Imóvel** está modelado como log de auditoria (`id, serviço, data, categoria, resumo`). Para virar diferencial competitivo real, precisa ser um prontuário: `Imóvel → Ambiente → Item → Intervenção → Garantia → Fotos → Documentos → Observações`.
- **Chat** submodelado (`texto, anexo`). Domínio provavelmente exige: remetente, leitura, mensagens de sistema, evidências, fotos, mensagens automáticas, alterações de status.

## Event Storming, proposta de 4 artefatos (não 1)

1. **Event Storm**, linha do tempo de eventos de negócio.
2. **Commands**, `CriarSolicitação`, `EnviarProposta`, `AceitarProposta`, `CancelarServiço`, `FinalizarServiço`.
3. **Policies**, reações automáticas (ex: proposta aceita → cancela demais → cria serviço → reserva pagamento → cria agenda).
4. **Read Models**, Dashboard Cliente, Dashboard Profissional, Histórico, Financeiro (caminho para CQRS futuro, sem precisar implementar agora).

## Kit para IA, ajuste de proposta

Trocar `13-prompts-ia.md` por **`13-domain-rules.md`**: regras de negócio explícitas (ex: "uma solicitação nunca pode ter duas propostas aceitas", "pagamento só libera após aprovação"), mais útil para geração assistida por IA do que prompts.

## Reordenação proposta (abordagem DDD)

```
01-visao-geral
02-event-storm
03-state-machine
04-regras-dominio
05-modelo-dados
06-openapi
07-arquitetura
08-interface
09-design-system
10-adrs
11-planejamento
12-qa
```

Inverte a ordem tradicional (funcionalidades → telas) para: eventos de negócio → estados do domínio → regras invariantes → modelo de dados → contratos (API) → arquitetura → interface. A interface passa a refletir o domínio, não o contrário.

> **Nota (2026-08-17): esta reordenação não foi adotada.** Decisão do PO foi manter a numeração original de `specifications/` intacta (`01, 02, 04-08`, renumerar quebraria referências cruzadas e histórico de Git) e acrescentar as camadas `foundation/` e `adr/` por cima, sem tocar na numeração existente. Este documento fica como registro histórico da análise que motivou `00-domain-invariants.md`, não como taxonomia vigente, não existem "duas numerações concorrentes" em uso, só esta proposta descartada e a estrutura real (`foundation/specifications/adr`). `specifications/` nunca teve um `03-*.md` próprio, não há histórico anterior a este repositório para confirmar a razão original; tratar como numeração herdada, não como lacuna introduzida pela reestruturação.

## Recomendação principal: `00-domain-invariants.md`

Documento com as regras que **nunca podem ser violadas**, o núcleo do domínio, para evitar que devs/agentes de IA diferentes implementem regras conflitantes em partes distintas do sistema. Exemplos citados:

- Uma solicitação pode ter várias propostas, mas apenas uma pode ser aceita.
- A aceitação de uma proposta invalida automaticamente todas as demais.
- Um serviço só pode existir após uma proposta aceita.
- Um pagamento nunca pode ser liberado antes da conclusão e aprovação do serviço (salvo exceções administrativas registradas em auditoria).
- Toda alteração de estado deve ser registrada em auditoria.
- Um serviço em garantia não pode gerar nova cobrança pela mesma intervenção.

Se apenas um documento adicional fosse escolhido além dos 8 originais, seria este.
