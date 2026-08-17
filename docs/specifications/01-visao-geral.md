# 01, Visão Geral e Negócio

> Documento gerado a partir do briefing de origem (conversa de ideação do produto). Nível de detalhamento: especificação de arquiteto sênior, servindo de base para os documentos 02–08.

## 1. Resumo Executivo

**Resolve+** é um marketplace de serviços residenciais e prediais (elétrica, hidráulica, pintura, montagem, ar-condicionado, limpeza, jardinagem, pequenos reparos) cujo diferencial não é apenas conectar cliente e profissional, a plataforma **participa da transação inteira**: orçamento assistido por IA, comparação de propostas sobre o mesmo escopo, pagamento protegido, garantia digital registrada e um histórico de manutenção do imóvel ("Prontuário do Imóvel") que gera recorrência.

A promessa de marca: **"Do problema à solução, com preço, profissional e garantia no mesmo lugar."**

Modelo de receita combina comissão por serviço (~10%), assinatura profissional (plano Pro) e, em fases posteriores, venda de materiais de construção associados ao serviço e um produto B2B (Resolve Empresas) para redes de lojas, condomínios e imobiliárias.

## 2. Contexto de Negócio

O mercado de contratação de mão de obra para reparos e manutenção residencial/predial hoje é resolvido por meios informais (indicação, grupos de WhatsApp, classificados) ou por marketplaces de leads que **encerram sua responsabilidade na indicação do contato**, não participam do orçamento, da execução, do pagamento nem da garantia. Isso deixa três dores sem solução estrutural:

1. **Confiança**: não há como saber, antes de contratar, se o profissional é competente e idôneo.
2. **Preço comparável**: orçamentos de profissionais diferentes cobrem escopos diferentes, tornando a comparação inútil.
3. **Garantia**: quando o serviço tem problema depois de concluído, não há registro formal do que foi feito, por quem e com qual cobertura.

## 3. Problema

Cliente residencial/predial não tem, num único lugar, a capacidade de: (a) descrever um problema de forma estruturada, (b) receber uma estimativa de preço confiável antes de falar com qualquer profissional, (c) comparar propostas equivalentes, (d) pagar com proteção contra serviço não concluído, e (e) manter um histórico de manutenção do seu imóvel que sirva de prova em caso de disputa ou revenda do imóvel.

## 4. Oportunidade

Transformar um marketplace de leads em uma **plataforma transacional completa**: orçamento inteligente + marketplace + pagamento protegido + garantia registrada + histórico de imóvel + (futuro) venda de materiais. Cada camada aumenta o custo de sair da plataforma tanto para cliente quanto para profissional, o que ataca diretamente o maior risco do modelo, a desintermediação ("fechar por fora").

A extensão para **Resolve Empresas** (redes de lojas, condomínios, imobiliárias, construtoras com manutenção recorrente) é identificada como possivelmente mais lucrativa que o consumidor residencial, por ticket médio maior e recorrência contratual.

## 5. Público-Alvo

### 5.1 Cliente (lado da demanda)
- Pessoa física, proprietário ou responsável por imóvel residencial, com necessidade pontual ou recorrente de manutenção.
- Pessoa jurídica (Fase 3+): gestores de redes de lojas, síndicos de condomínio, imobiliárias, construtoras.

### 5.2 Profissional (lado da oferta)
- Autônomo ou pequena empresa prestadora de serviço nas categorias suportadas (eletricista, encanador, pedreiro, pintor, montador, técnico de ar-condicionado, limpeza, jardinagem, pequenos reparos).
- Segmentado por nível de confiança dentro da plataforma (ver §Sistema de Confiança em 02-funcionalidades.md).

## 6. Personas

| ID | Persona | Perfil | Objetivo principal | Frustração hoje |
|----|---------|--------|---------------------|------------------|
| PERSONA-01 | Cliente Residencial Reativo | Precisa resolver um problema urgente (ex.: tomada queimando) | Resolver rápido, com confiança e preço justo | Não sabe quem chamar, medo de ser enganado no preço |
| PERSONA-02 | Cliente Residencial Preventivo | Já usou o app antes, mantém o imóvel | Recorrência de manutenção, histórico organizado | Esquece prazos de manutenção, não tem registro dos serviços anteriores |
| PERSONA-03 | Profissional Autônomo | Eletricista/encanador/pintor independente | Receber demanda qualificada, ser pago com segurança | Cliente soma pelo preço, calote, falta de prova do serviço prestado |
| PERSONA-04 | Gestor de Rede/Facilities (B2B) | Responsável por manutenção de múltiplas unidades | Centralizar chamados, controlar custo e aprovação | Processo manual, sem visibilidade consolidada de custos e fornecedores |

## 7. Objetivos do MVP

- OBJ-MVP-01: Permitir que o cliente registre um problema (foto/áudio/texto) e receba uma faixa de preço estimada antes de contratar.
- OBJ-MVP-02: Conectar o cliente a profissionais verificados da categoria certa, na região certa.
- OBJ-MVP-03: Padronizar o escopo do serviço para que propostas de profissionais diferentes sejam comparáveis.
- OBJ-MVP-04: Processar pagamento protegido (retido até conclusão/aceite do serviço).
- OBJ-MVP-05: Registrar garantia digital do serviço executado (fotos antes/depois, descrição, prazo).
- OBJ-MVP-06: Validar o modelo em **uma cidade**, com 4–5 categorias de profissionais, antes de expandir.

## 8. Objetivos de Negócio

- OBJ-NEG-01: Gerar receita via comissão por serviço concluído (percentual-alvo inicial: 8–15%).
- OBJ-NEG-02: Criar recorrência via Prontuário do Imóvel (retenção além do momento de problema pontual).
- OBJ-NEG-03: Reduzir a taxa de "fechamento por fora" a um nível que não inviabilize a unit economics (mecanismo proposto em `09-mecanismo-antidesintermediacao.md`, ainda não validado por Produto/Jurídico).
- OBJ-NEG-04: Viabilizar, a partir da Fase 2, receita adicional por venda de materiais de construção associados ao serviço.
- OBJ-NEG-05: Abrir, a partir da Fase 3, uma frente B2B (Resolve Empresas) com contratos recorrentes.

## 9. Objetivos Técnicos

- OBJ-TEC-01: Suportar captura estruturada de solicitação (mídia + IA) com baixa fricção para o cliente.
- OBJ-TEC-02: Gerar estimativa de preço a partir de região, categoria e histórico de serviços, sem exigir motor de pricing complexo no MVP (heurística inicial, evoluindo para modelo orientado a dados).
- OBJ-TEC-03: Garantir rastreabilidade completa do ciclo de vida do serviço (solicitação → proposta → aceite → execução → pagamento → garantia), com estados explícitos (detalhado em 02-funcionalidades.md e 05-arquitetura.md).
- OBJ-TEC-04: Suportar pagamento protegido (escrow) com liberação condicionada a evento de conclusão/aceite.
- OBJ-TEC-05: Arquitetura que comporte, sem retrabalho estrutural, a expansão para materiais (Fase 2) e B2B (Fase 3).

## 10. Escopo (MVP)

Incluído:
- Cadastro de cliente e profissional, com verificação de identidade do profissional.
- Localização e raio de atendimento.
- Registro de solicitação com mídia (foto/vídeo/texto) e apoio de IA para estruturar o escopo.
- Geração de estimativa de preço por faixa.
- Envio de oportunidade a profissionais compatíveis (categoria + região).
- Propostas comparáveis (mesmo escopo, mão de obra/material/prazo/garantia separados).
- Chat cliente-profissional dentro do app.
- Agenda / agendamento do serviço.
- Pagamento protegido dentro do app.
- Avaliação pós-serviço.
- Registro de garantia digital (fotos antes/depois, descrição, prazo).
- Categorias iniciais: elétrica, hidráulica, pintura, pequenos reparos, montagem.
- Cidade única de lançamento.

## 11. Fora do Escopo (MVP)

- Venda de materiais de construção integrada (Fase 2).
- Resolve Empresas / módulo B2B multi-unidade (Fase 3).
- Categorias adicionais (pedreiro estrutural, telhadista, ar-condicionado, jardinagem) além das 5 iniciais.
- Motor de pricing orientado a dados/ML (MVP usa heurística por região+categoria+histórico simples).
- Nota fiscal/recibo fiscal automatizado (avaliar dependência jurídica/contábil antes de comprometer prazo, ver bloqueadores em 07-engenharia.md).
- Cashback/pontos e programas de fidelidade.
- Expansão multi-cidade.

## 12. Premissas

- Existe oferta suficiente de profissionais dispostos a se cadastrar e passar por verificação na cidade de lançamento.
- O cliente aceita pagar uma faixa de preço "estimada" (não fechada) antes de escolher o profissional.
- É possível impedir/desincentivar fechamento por fora o suficiente para sustentar a comissão (ver RN e mitigação em 02-funcionalidades.md, este ponto é tratado como **risco central do modelo**, não um detalhe).
- Pagamento protegido (escrow) é operacionalmente viável via parceiro de pagamento (adquirente/PSP) desde o MVP.

## 13. Restrições

- Orçamento e prazo de lançamento ainda não formalizados neste documento (ver 08-planejamento.md).
- Enquadramento jurídico da "garantia digital" (é garantia contratual da plataforma, do profissional, ou ambos?) ainda não resolvido, **bloqueador de arquitetura**, não deve ser tratado como detalhe editorial (ver 07-engenharia.md).
- Modelo de repasse ao profissional (quando exatamente o profissional recebe: na conclusão, no aceite do cliente, ou em prazo fixo pós-conclusão) impacta diretamente o modelo de dados e o fluxo financeiro, precisa de decisão antes da modelagem (ver 04-modelo-dados.md).

## 14. Glossário Inicial

| Termo | Definição |
|---|---|
| Resolve+ | Nome provisório do produto/plataforma. |
| Prontuário do Imóvel | Histórico digital de serviços executados em um imóvel específico, vinculado ao endereço/unidade, não à pessoa. |
| Orçamento Inteligente | Estimativa de preço gerada a partir de categoria, região e histórico de serviços similares, antes da escolha do profissional. |
| Escopo Padronizado | Descrição estruturada do serviço solicitado, usada como base comum para todas as propostas recebidas, permite comparação direta entre profissionais. |
| Pagamento Protegido | Modalidade em que o valor pago pelo cliente fica retido pela plataforma até a conclusão/aceite do serviço (escrow). |
| Garantia Digital | Registro formal, dentro da plataforma, de cobertura pós-serviço (prazo, escopo coberto, evidências antes/depois). |
| Nível de Confiança | Classificação do profissional (Verificado → Bronze → Prata → Ouro → Elite) baseada em avaliações, volume de serviços, cancelamentos e reincidência de reclamação. |
| Resolve Empresas | Módulo B2B para clientes com múltiplas unidades (redes de lojas, condomínios, imobiliárias, construtoras) com painel consolidado de chamados e custos. |
| Fechar por fora | Cliente e profissional combinarem o serviço fora da plataforma após o contato inicial, para evitar a comissão, risco central do modelo de negócio. |

---
*Fonte: briefing de ideação do produto (conversa original). Próximo documento: `02-funcionalidades.md`.*
