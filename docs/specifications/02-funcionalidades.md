# 02: Funcionalidades

## 1. Casos de Uso

| ID | Caso de Uso | Atores |
|---|---|---|
| UC001 | Criar conta | Cliente, Profissional |
| UC002 | Autenticar | Cliente, Profissional |
| UC003 | Recuperar senha | Cliente, Profissional |
| UC004 | Editar perfil | Cliente, Profissional |
| UC005 | Solicitar serviço | Cliente |
| UC006 | Anexar fotos | Cliente |
| UC007 | Selecionar categoria | Cliente |
| UC008 | Receber solicitações | Profissional |
| UC009 | Enviar proposta | Profissional |
| UC010 | Comparar propostas | Cliente |
| UC011 | Contratar profissional | Cliente |
| UC012 | Conversar via chat | Cliente, Profissional |
| UC013 | Agendar serviço | Cliente, Profissional |
| UC014 | Executar serviço | Profissional |
| UC015 | Registrar conclusão | Profissional |
| UC016 | Confirmar conclusão | Cliente |
| UC017 | Liberar pagamento | Sistema |
| UC018 | Avaliar serviço | Cliente |
| UC019 | Registrar garantia | Sistema |
| UC020 | Consultar histórico do imóvel | Cliente |

## 2. Jornada do Cliente

Abrir aplicativo → Login/Cadastro → Escolher categoria → Descrever problema → Enviar fotos → Confirmar endereço → Ver faixa de preço estimada → Criar solicitação → Profissionais recebem → Receber propostas → Comparar propostas → Selecionar profissional → Chat → Agendamento → Execução → Conclusão → Aceite → Pagamento liberado → Avaliação → Serviço entra no histórico

## 3. Jornada do Profissional

Login → Receber oportunidade → Analisar solicitação → Enviar proposta → Aguardar aceite → Receber contratação → Conversar com cliente → Executar serviço → Registrar fotos → Finalizar → Receber pagamento

## 4. Fluxos Principais

**FP001, Solicitar serviço**
1. Cliente escolhe categoria.
2. Informa descrição e preenche o `escopo` do `template_escopo`.
3. Anexa imagens.
4. Informa endereço (imóvel).
5. Sistema calcula e exibe faixa de preço estimada (`POST /requests/estimate`, `10-motor-precificacao.md`). A faixa é informativa, não é proposta.
6. Cliente confirma; sistema cria a solicitação com snapshot da faixa (`POST /requests`).
7. Profissionais elegíveis recebem notificação.

**FP002, Contratação**
1. Cliente recebe propostas.
2. Compara valores.
3. Escolhe profissional.
4. Sistema cria contratação.
5. Chat é habilitado.

**FP003, Execução**
1. Profissional comparece.
2. Executa serviço.
3. Envia evidências.
4. Cliente confirma.
5. Sistema libera pagamento.
6. Garantia é registrada.

## 5. Fluxos Alternativos

- **FA001**, Nenhum profissional respondeu: sistema informa indisponibilidade e mantém solicitação aberta por período configurável.
- **FA002**, Cliente cancela antes da contratação: solicitação encerrada.
- **FA003**, Profissional cancela: solicitação retorna para busca de novos profissionais.
- **FA004**, Cliente rejeita conclusão: pagamento permanece bloqueado, status muda para "Em Contestação" (`PaymentDispute.tipo = CONTESTACAO_CONCLUSAO`). Mediação: Admin no MVP, prazo `DISPUTE_MEDIATION_DAYS` = 7d, timeout → `Aprovado` (`foundation/03-cancellation-rules.md`, B003).

## 6. Requisitos Funcionais

| ID | Descrição | Prioridade | Origem | Dependências |
|---|---|---|---|---|
| RF001 | Permitir cadastro de cliente | Alta | Conversa | Nenhuma |
| RF002 | Permitir cadastro de profissional com verificação documental (upload, revisão manual pelo Admin, `SEGURO_RC` obrigatório (B005), transição para `ATIVA` quando slots exigidos aprovados; ver `04-modelo-dados.md` §DocumentoProfissional e INV-002) | Alta | Conversa | RF001 |
| RF003 | Autenticação | Alta | Inferência | RF001 |
| RF004 | Recuperação de senha | Média | Inferência | RF003 |
| RF005 | Editar perfil | Alta | Inferência | RF001 |
| RF006 | Cadastro de endereço | Alta | Conversa | RF001 |
| RF007 | Cadastro de categorias | Alta | Conversa | Admin |
| RF008 | Criar solicitação | Alta | Conversa | RF006, RF029 |
| RF009 | Upload de fotos | Alta | Conversa | RF008 |
| RF010 | Localizar profissionais próximos | Alta | Conversa | RF008 |
| RF011 | Notificar profissionais | Alta | Conversa | RF010 |
| RF012 | Receber propostas | Alta | Conversa | RF011 |
| RF013 | Comparar propostas | Alta | Conversa | RF012 |
| RF014 | Contratar profissional | Alta | Conversa | RF013 |
| RF015 | Criar chat | Alta | Conversa | RF014 |
| RF016 | Agendar atendimento | Alta | Conversa | RF014 |
| RF017 | Registrar início do serviço | Média | Inferência | RF016 |
| RF018 | Registrar conclusão | Alta | Conversa | RF017 |
| RF019 | Upload de fotos finais | Alta | Conversa | RF018 |
| RF020 | Confirmar conclusão | Alta | Conversa | RF018 |
| RF021 | Liberar pagamento | Alta | Conversa | RF020 |
| RF022 | Avaliar profissional | Alta | Conversa | RF021 |
| RF023 | Gerar garantia | Alta | Conversa | RF020 |
| RF024 | Registrar histórico do imóvel | Alta | Conversa | RF023 |
| RF025 | Consultar histórico | Média | Conversa | RF024 |
| RF026 | Sistema de reputação | Média | Conversa | RF022 |
| RF027 | Notificações push | Média | Inferência | RF011 |
| RF028 | Painel administrativo | Alta | Inferência | Todos |
| RF029 | Gerar e exibir faixa de preço estimada (heurística tabela+escopo, sem ML) | Alta | OBJ-MVP-01 | RF006, RF007 |

## 7. Regras de Negócio

| ID | Regra |
|---|---|
| RN001 | Qualquer profissional pode visualizar solicitações abertas compatíveis com suas categorias (`GET /requests/available`), independente do status de verificação. Só profissionais verificados (`status = ATIVA`) podem enviar proposta. Antes de 2026-08-22 o gate de `ATIVA` cobria as duas coisas (INV-002, `00-domain-invariants.md`). |
| RN002 | Cliente só pode contratar uma proposta por solicitação. |
| RN003 | Cartão: pagamento é autorizado no aceite da proposta e só é capturado **integralmente** após conclusão e aprovação. Pix: nasce `PENDENTE` no aceite, vira `CAPTURADO` quando o webhook do Asaas confirma o pagamento (INV-047, corrigido em 2026-08-20; não é captura imediata), e `Agendado → Em Andamento` fica bloqueado enquanto `PENDENTE` (INV-048); só o **repasse** do serviço executado espera aprovação (`adr/ADR-005-gateway-pagamento.md`, INV-041). Exceção Cenário B: multa pode ser capturada/repassada sem `APROVADO` (`foundation/03-cancellation-rules.md`). Não é escrow bancário, é autorizar→capturar→repassar no cartão (ver `adr/ADR-002-financeiro.md`). Redigido em 2026-08-16 com vocabulário de escrow ("retido"), corrigido em 2026-08-17, ajustado para Pix e B003 em 2026-08-17, corrigido para o Pix real (`PENDENTE`→webhook) em 2026-08-20. |
| RN004 | Avaliação só é permitida com o Serviço em `APROVADO` (não existe estado "concluído" separado, ver `foundation/02-state-machine.md`). |
| RN005 | Todo Serviço que atinge `APROVADO` gera garantia (INV-050). |
| RN006 | Todo Serviço que atinge `APROVADO` entra no prontuário do imóvel via `Intervention` (INV-060). |
| RN007 | Profissionais possuem nível de confiança (`PerfilProfissional.nivel_confianca`: Verificado → Bronze → Prata → Ouro → Elite) calculado a partir de serviços aprovados, nota média, taxa de cancelamento, tempo de conta e reclamações recentes. Limiares em `foundation/05-trust-level.md`. |
| RN008 | Cancelamentos imputáveis ao profissional entram em `taxa_cancelamento_pct` e podem rebaixar o nível de confiança (limiares em `foundation/05-trust-level.md`). |
| RN009 | Fotos podem ser exigidas na conclusão. |
| RN010 | Serviço só pode ser encerrado após confirmação ou prazo de aceite automático de 72h sem contestação (`AUTO_APPROVAL_HOURS`, `adr/ADR-004-prazo-aceite-automatico.md`). |
| RN026 | O nível de confiança aparece como badge no perfil público do profissional e como critério de desempate na ordenação de profissionais elegíveis (RF010), após proximidade geográfica. |
| RN011 | Profissional com `Conta.status = PENDENTE_VERIFICACAO` não recebe solicitações nem envia propostas; só transiciona para `ATIVA` quando todos os documentos exigidos (base + adicionais por categoria declarada) estiverem `APROVADO` por Admin (RF002, INV-002). |

## 7.1 RF002, Verificação documental do profissional

Fluxo MVP (revisão manual, sem verificação automatizada):

1. Profissional se cadastra (`POST /auth/register`, `tipo = PROFISSIONAL`) e nasce com `status = PENDENTE_VERIFICACAO`.
2. Declara `categorias_atendidas` (ao menos uma das 5 do MVP) no perfil.
3. Envia uploads por slot exigido (lista base + `CERTIFICADO_NR10` se declara Elétrica), cada um gera `DocumentoProfissional` com `status = PENDENTE`.
4. Admin revisa no painel (RF028): aprova (`APROVADO`) ou reprova (`REJEITADO` + motivo).
5. Quando todos os slots exigidos estão `APROVADO`, sistema transiciona `Conta.status` para `ATIVA` e emite `ProfissionalVerificado` (habilita RF008–RF012, INV-002).

Documentos exigidos, critérios de aprovação e enums: `04-modelo-dados.md` §DocumentoProfissional. Máquina de estados dos documentos: `foundation/02-state-machine.md` §7.

## 8. Perfis de Usuário

**Visitante**
- Criar conta
- Login

**Cliente**
- Solicitar serviço
- Ver faixa de preço estimada
- Receber propostas
- Contratar
- Conversar
- Pagar
- Avaliar
- Consultar histórico

**Profissional**
- Editar perfil
- Receber oportunidades
- Enviar propostas
- Conversar
- Executar serviços
- Registrar conclusão

**Administrador**
- Gerenciar usuários
- Gerenciar categorias
- Gerenciar tabelas de preço
- Revisar documentos de profissionais (RF002)
- Moderar avaliações
- Resolver disputas
- Visualizar métricas
- Suspender contas

## 9. Permissões

| Ação | Cliente | Profissional | Admin |
|---|---|---|---|
| Criar solicitação | ✔ | ✖ | ✔ |
| Pré-visualizar estimativa de preço | ✔ | ✖ | ✔ |
| Gerenciar tabela de preço | ✖ | ✖ | ✔ |
| Enviar proposta | ✖ | ✔ | ✖ |
| Contratar | ✔ | ✖ | ✔ |
| Chat | ✔ | ✔ | ✔ |
| Finalizar serviço | ✖ | ✔ | ✔ |
| Confirmar conclusão | ✔ | ✖ | ✔ |
| Avaliar | ✔ | ✖ | ✔ |
| Gerenciar usuários | ✖ | ✖ | ✔ |
| Revisar documentos profissional | ✖ | ✖ | ✔ |

## 10. Estados do Sistema

> Este documento é o desenho original (2026-08-16), anterior à revisão de invariantes. Fonte de verdade de transição de estados é `foundation/02-state-machine.md`; abaixo mantido como registro histórico, com a linha de Pagamento corrigida em 2026-08-17 para não usar vocabulário de escrow (rejeitado por `adr/ADR-002-financeiro.md`).

**Solicitação**: Criada → Aberta → Recebendo propostas → Expirada / Cancelada / Contratada

**Serviço**: Agendado → Em andamento → Aguardando Aprovação → Aprovado / Em Contestação → Cancelado

**PaymentAuthorization.status** (4 valores): Autorizado → Capturado | Cancelado | Expirado → (nova autorização, evento Reautorizado, INV-046). `Capturado` é terminal para o status.

**PaymentEvent.tipo sobre autorização Capturado** (histórico, não status): Repassado, Reembolsado, ver `foundation/02-state-machine.md` §4b, corrigido em 2026-08-17 para não misturar as duas máquinas.

**Garantia**: Ativa → Expirada → Acionada → Encerrada

**Conta**: Pendente de verificação → Ativa → Suspensa → Bloqueada → Excluída

## 11. Pendências para Validação

- Prazo máximo para envio de propostas.
- Quantidade máxima de propostas por solicitação.
- Critérios para distribuição das solicitações aos profissionais.
- Política de cancelamento e multas.
- Tempo de retenção do pagamento.
- Fluxo de mediação de disputas.
- Regras para garantia (prazo por categoria, cobertura e exclusões).
- Política de aceite automático caso o cliente não responda.
- Limites de anexos, formatos e tamanho de arquivos.
