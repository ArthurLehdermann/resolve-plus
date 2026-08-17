# 04 — Decisions Pending (Bloqueadores Formais)

> Bloqueadores de arquitetura/negócio, com status explícito. Nenhum item aqui é "detalhe a resolver depois" — são pontos que **mudam** modelo de dados, API ou fluxo financeiro dependendo da resposta. Registrado pelo PO em 2026-08-16.

## B001 — Responsabilidade da Garantia

**Status:** Bloqueado

**Impacta:** Modelo de Dados · APIs · Fluxo Financeiro · Termos de Uso

**Alternativas:**
- Profissional responde sozinho pela garantia (Modelo A).
- Plataforma assume o risco financeiro da garantia (Modelo C).
- Responsabilidade compartilhada — profissional executa e responde primeiro, plataforma media (Modelo B).

**Recomendação registrada (não é decisão final):** Modelo B — reduz risco jurídico da plataforma e evita que ela vire seguradora, mantendo confiança do cliente.

**Responsável:** Jurídico + Produto.

---

## B002 — Prazo para Aceite Automático / Repasse

**Status:** Bloqueado

**Impacta:** State Machine · Fluxo Financeiro · Pagamento · Notificações

**Alternativas:** 24h · 48h · 72h · sem aceite automático.

**Preferência provisória do PO:** 72 horas — precisa validação de Produto.

Relacionado a `INV-031` e `INV-041` (`00-domain-invariants.md`): o pagamento só é capturado/repassado após aprovação do cliente **ou** o esgotamento desta janela sem contestação.

**Responsável:** Produto.

---

## B003 — Cancelamento

**Status:** Bloqueado

Falta definir:
- Quem pode cancelar (cliente, profissional, ambos, admin).
- Até quando (antes do agendamento, durante execução, depois).
- Multa aplicável.
- Estorno parcial.
- Impacto na reputação.
- Impacto na garantia.
- Impacto na agenda.

Merece um documento de regras próprio quando destravado, referenciado a partir de `00-domain-invariants.md` — não antecipar aqui.

**Responsável:** Produto + Jurídico.

---

## B004 — Histórico Manual do Imóvel

**Status:** Bloqueado (decisão provisória do PO já registrada, falta validação)

**Alternativas avaliadas:**
- **A — Somente registros gerados pela plataforma.** Prós: dados confiáveis. Contras: histórico incompleto.
- **B — Permitir registros manuais.** Prós: aumenta o valor do prontuário. Contras: reduz confiabilidade.

**Decisão provisória do PO (2026-08-16): modelo híbrido.** Todo registro tem uma `origem` (`PLATAFORMA | MANUAL | IMPORTADO`) com selo de confiabilidade próprio — um comprador futuro do imóvel distingue manutenção comprovada por serviço contratado na plataforma de anotação manual do proprietário. Refletido em `00-domain-invariants.md` (INV-062).

**Responsável:** Produto (validar antes de fechar `specifications/04-modelo-dados.md`).

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Criação a partir da revisão do PO sobre a estrutura de `foundation/`. |
