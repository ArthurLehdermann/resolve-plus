# 01 — Event Storm

> v0.1 — rascunho construído a partir das jornadas e fluxos já mapeados em `specifications/02-funcionalidades.md` (UC001–UC020, FP001–FP003, FA001–FA004) e das invariantes em `00-domain-invariants.md`. Falta validação do PO/Produto sobre exceções ainda não descritas (disputa formal, garantia acionada em detalhe).

## Como ler

Eventos de domínio (passado, o que já aconteceu) → Commands que os disparam → Policies (reações automáticas) → Read Models que consomem o resultado. Convenção: `Evento` em CamelCase terminando em particípio passado.

## 1. Linha do tempo principal (caminho feliz)

| # | Command | Ator | Evento | Policy disparada |
|---|---|---|---|---|
| 1 | CriarConta | Cliente/Profissional | `ContaCriada` | Enviar verificação |
| 2 | VerificarProfissional | Sistema/Admin | `ProfissionalVerificado` | Habilita recebimento de solicitações (INV-002) |
| 3 | CriarSolicitacao | Cliente | `SolicitacaoCriada` | Notificar profissionais elegíveis na categoria/raio |
| 4 | EnviarProposta | Profissional | `PropostaEnviada` | Notificar cliente |
| 5 | AceitarProposta | Cliente | `PropostaAceita` | **P1** Recusar automaticamente as demais propostas (INV-011) · **P2** Criar `Serviço` a partir da proposta (INV-020/021) · **P3** Habilitar chat |
| 6 | AgendarServico | Cliente+Profissional | `ServicoAgendado` | — |
| 7 | IniciarExecucao | Profissional | `ExecucaoIniciada` | — |
| 8 | RegistrarConclusao | Profissional | `ConclusaoRegistrada` | Notificar cliente, iniciar janela de aceite automático (B002) |
| 9 | ConfirmarConclusao **ou** expirar janela | Cliente / Sistema | `ServicoAprovado` | **P4** Capturar pagamento (INV-041) · **P5** Emitir garantia (INV-050) |
| 10 | (interno) | Sistema | `PagamentoCapturado` | **P6** Agendar repasse após janela de contestação (B002) |
| 11 | (interno) | Sistema | `PagamentoRepassado` | — |
| 12 | (interno) | Sistema | `InterventionRegistrada` | Anexa ao prontuário do imóvel (INV-060/061) |
| 13 | AvaliarServico | Cliente | `AvaliacaoRegistrada` | Atualiza reputação do profissional (RN007) |

## 2. Eventos de exceção (fluxos alternativos)

| Evento | Origem | Efeito |
|---|---|---|
| `SolicitacaoExpirada` | Sistema (FA001, nenhum profissional respondeu) | Solicitação permanece consultável, não recebe mais propostas |
| `SolicitacaoCanceladaPeloCliente` | Cliente (FA002) | Só antes de proposta aceita; nenhuma penalidade definida (ver B003) |
| `PropostaRecusadaAutomaticamente` | Sistema | Disparado por P1 quando outra proposta é aceita (INV-011) |
| `ServicoContestado` | Cliente (FA004) | Bloqueia captura de pagamento; fluxo de mediação **Necessita Validação** (B003) |
| `ServicoCancelado` | Cliente/Profissional/Admin | Não gera garantia nem libera pagamento normal (INV-032); pode gerar reembolso parcial — regra pendente (B003) |
| `GarantiaAcionada` | Cliente | Sempre com evidências (INV-052); fluxo de responsabilidade pendente (B001) |
| `ContaSuspensa` / `ContaBloqueada` | Admin | Cancela participação em processos em andamento sem apagar histórico consolidado (INV-003) |

## 3. Policies (reações automáticas) — resumo

| ID | Gatilho | Ação |
|---|---|---|
| P1 | `PropostaAceita` | Recusar as demais propostas da solicitação (INV-011) |
| P2 | `PropostaAceita` | Criar `Serviço` (evento, não entidade `Contratação` — INV-020) |
| P3 | `PropostaAceita` | Habilitar chat entre cliente e profissional |
| P4 | `ServicoAprovado` | Capturar pagamento autorizado (INV-041) |
| P5 | `ServicoAprovado` | Emitir garantia (INV-050) |
| P6 | `PagamentoCapturado` | Agendar repasse ao profissional após janela de contestação (B002) |
| P7 | `InterventionRegistrada` (implícita em `ServicoAprovado`) | Anexar ao prontuário do imóvel, com `origem: PLATAFORMA` (INV-060/061/062) |

## 4. Read Models (consumo, não implementação)

- **Dashboard Cliente** — solicitações abertas, propostas recebidas, serviços em andamento, histórico do imóvel.
- **Dashboard Profissional** — oportunidades, propostas enviadas, agenda, repasses pendentes/recebidos.
- **Histórico/Prontuário do Imóvel** — `Intervention` por `Area`/`Asset`, com `origem` e selo de confiabilidade (INV-061/062).
- **Financeiro** — extrato de `PaymentEvent` por usuário (cliente vê retenção/captura, profissional vê repasse).
- **Painel Admin** — visão operacional cross-entidade para moderação e disputas (UC020, RF028).

## Pendências deste documento

- Fluxo de disputa/mediação formal ainda não tem eventos próprios (depende de B003).
- Evento de garantia acionada não detalha quem decide o desfecho (depende de B001).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | v0.1 — criado a partir de `specifications/02-funcionalidades.md` e `00-domain-invariants.md`. |
