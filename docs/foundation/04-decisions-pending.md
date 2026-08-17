# 04: Decisions Pending (Bloqueadores Formais)

> Bloqueadores de arquitetura/negócio, com status explícito. Nenhum item aqui é "detalhe a resolver depois", são pontos que **mudam** modelo de dados, API ou fluxo financeiro dependendo da resposta. Registrado pelo PO em 2026-08-16.

## B001, Responsabilidade da Garantia (inclui o mecanismo financeiro que a lastreia)

**Status:** Bloqueado. Ampliado em 2026-08-17 (quarta revisão do PO) para incluir a pergunta financeira/regulatória abaixo, antes um bloqueador separado, "e agora é um item só" (PO).

**Impacta:** Modelo de Dados · APIs · Fluxo Financeiro · Termos de Uso · Enquadramento Regulatório

**Alternativas (responsabilidade):**
- Profissional responde sozinho pela garantia (Modelo A).
- Plataforma assume o risco financeiro da garantia (Modelo C).
- Responsabilidade compartilhada, profissional executa e responde primeiro, plataforma media (Modelo B).

**Recomendação registrada (não é decisão final):** Modelo B, reduz risco jurídico da plataforma e evita que ela vire seguradora, mantendo confiança do cliente.

**Pergunta financeira/regulatória (bloqueador separado até 2026-08-17, fundido aqui na mesma data):** `ADR-003-garantia.md` propõe lastrear a garantia retendo uma fração do repasse ao profissional (`valor_reserva_garantia`) por até o prazo de garantia (pode passar de 90 dias). `adr/ADR-002-financeiro.md` rejeitou escrow bancário especificamente para não reter dinheiro de terceiro, reter parte do repasse do profissional por prazo determinado após a captura tem o mesmo enquadramento. Os dois ADRs precisam do mesmo parecer jurídico, não podem ser decididos em separado: se reter o repasse não é viável, a alternativa a avaliar é lastrear a garantia com uma fração da comissão da própria plataforma (`valor_plataforma`, já é dela por direito, não é retenção de fundo de terceiro), ou reduzir a janela de exposição.

**Se Modelo B + retenção do repasse forem confirmados, fica pendente também:**
- Percentual de reserva: 10% · 15% · 20% do `valor_profissional` no split. Trade-off: percentual alto cobre mais risco de garantia, mas atrasa mais dinheiro do profissional (afeta adesão do lado profissional, ver `08-planejamento.md`, "aquisição de oferta"). Sem preferência provisória registrada, precisa dado de ticket médio por categoria.

**Responsável:** Jurídico + Produto + Financeiro.

---

## B002, Prazo para Aceite Automático / Repasse

**Status:** Bloqueado

**Impacta:** State Machine · Fluxo Financeiro · Pagamento · Notificações

**Alternativas:** 24h · 48h · 72h · sem aceite automático.

**Preferência provisória do PO:** 72 horas, precisa validação de Produto.

Relacionado a `INV-031` e `INV-041` (`00-domain-invariants.md`): o pagamento só é capturado/repassado após aprovação do cliente **ou** o esgotamento desta janela sem contestação.

**Responsável:** Produto.

---

## B003, Cancelamento

**Status:** Bloqueado

Falta definir:
- Quem pode cancelar (cliente, profissional, ambos, admin).
- Até quando (antes do agendamento, durante execução, depois).
- Multa aplicável.
- Estorno parcial.
- Impacto na reputação.
- Impacto na garantia.
- Impacto na agenda.

Merece um documento de regras próprio quando destravado, referenciado a partir de `00-domain-invariants.md`, não antecipar aqui.

**Responsável:** Produto + Jurídico.

---

## B004, Histórico Manual do Imóvel

**Status:** Bloqueado (decisão provisória do PO já registrada, falta validação)

**Alternativas avaliadas:**
- **A, Somente registros gerados pela plataforma.** Prós: dados confiáveis. Contras: histórico incompleto.
- **B, Permitir registros manuais.** Prós: aumenta o valor do prontuário. Contras: reduz confiabilidade.

**Decisão provisória do PO (2026-08-16): modelo híbrido.** Todo registro tem uma `origem` (`PLATAFORMA | MANUAL | IMPORTADO`) com selo de confiabilidade próprio, um comprador futuro do imóvel distingue manutenção comprovada por serviço contratado na plataforma de anotação manual do proprietário. Refletido em `00-domain-invariants.md` (INV-062).

**Responsável:** Produto (validar antes de fechar `specifications/04-modelo-dados.md`).

## B005, Responsabilidade Civil por Dano ao Imóvel

**Status:** Bloqueado

**Impacta:** Termos de Uso · Fluxo Financeiro · Verificação de Profissional (RF002)

Nenhum documento até 2026-08-17 tratava de quem responde quando um profissional causa dano ao imóvel durante a execução (ex.: vazamento em serviço hidráulico, dano elétrico). É o maior risco reputacional de um marketplace presencial e ficou de fora de toda a análise financeira/jurídica até agora, identificado em revisão crítica do PO sobre `specifications/04-modelo-dados.md`.

**Responsável:** Jurídico + Produto.

---

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Criação a partir da revisão do PO sobre a estrutura de `foundation/`. |
| 2026-08-17 | Adiciona B005 (responsabilidade civil por dano ao imóvel), identificado em segunda revisão crítica do PO. |
| 2026-08-17 | Adiciona bloqueador de percentual de reserva financeira de garantia, identificado em terceira revisão crítica do PO (depois fundido em B001, ver linha seguinte). |
| 2026-08-17 | Quarta revisão do PO: funde o bloqueador de percentual de reserva em B001, mesmo parecer jurídico cobre responsabilidade da garantia e se a reserva financeira que a lastreia caracteriza retenção de fundo de terceiro (mesmo enquadramento que `ADR-002-financeiro.md` evitou). Não existe mais como item numerado separado. |
