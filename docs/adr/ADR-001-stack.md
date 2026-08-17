# ADR-001 — Stack Tecnológica

**Status:** Decidido

**Data:** 2026-08-16 (consolidado a partir de `specifications/08-planejamento.md`, seção "Decisões Técnicas Consolidadas")

## Contexto

Havia inconsistência editorial entre documentos: alguns tratavam a stack como decidida, outros como pendente (Grupo A de inconsistências apontado pelo PO). `specifications/08-planejamento.md` já lista a stack em "Decisões Técnicas Consolidadas" — este ADR formaliza isso como decisão única, encerrando a ambiguidade.

## Decisão

- **Mobile:** Flutter.
- **Backend:** Laravel, API REST versionada.
- **Banco principal:** PostgreSQL.
- **Cache/filas:** Redis.
- **Arquivos:** Object Storage.
- **Arquitetura:** monólito modular.
- **Chave primária:** UUID.
- **Exclusão:** Soft Delete em entidades críticas.
- **Processamento assíncrono:** via filas.
- **Autenticação:** Bearer Token.

## Consequências

- Documentos que ainda mencionarem a stack como "pendente" ou "em aberto" estão desatualizados e devem ser corrigidos para referenciar este ADR.
- Decisões de modelo de dados (`specifications/04-modelo-dados.md`) e arquitetura (`specifications/05-arquitetura.md`) partem desta stack como premissa fixa.

## Changelog

| Data | Mudança |
|---|---|
| 2026-08-16 | Criado para reconciliar inconsistência editorial (Grupo A). |
