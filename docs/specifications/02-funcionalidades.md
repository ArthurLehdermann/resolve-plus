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

Abrir aplicativo → Login/Cadastro → Escolher categoria → Descrever problema → Enviar fotos → Confirmar endereço → Criar solicitação → Profissionais recebem → Receber propostas → Comparar propostas → Selecionar profissional → Chat → Agendamento → Execução → Conclusão → Aceite → Pagamento liberado → Avaliação → Serviço entra no histórico

## 3. Jornada do Profissional

Login → Receber oportunidade → Analisar solicitação → Enviar proposta → Aguardar aceite → Receber contratação → Conversar com cliente → Executar serviço → Registrar fotos → Finalizar → Receber pagamento

## 4. Fluxos Principais

**FP001, Solicitar serviço**
1. Cliente escolhe categoria.
2. Informa descrição.
3. Anexa imagens.
4. Informa endereço.
5. Sistema cria solicitação.
6. Profissionais elegíveis recebem notificação.

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
- **FA004**, Cliente rejeita conclusão: pagamento permanece bloqueado, status muda para "Em Contestação". **Necessita Validação**: fluxo de mediação.

## 6. Requisitos Funcionais

| ID | Descrição | Prioridade | Origem | Dependências |
|---|---|---|---|---|
| RF001 | Permitir cadastro de cliente | Alta | Conversa | Nenhuma |
| RF002 | Permitir cadastro de profissional | Alta | Conversa | RF001 |
| RF003 | Autenticação | Alta | Inferência | RF001 |
| RF004 | Recuperação de senha | Média | Inferência | RF003 |
| RF005 | Editar perfil | Alta | Inferência | RF001 |
| RF006 | Cadastro de endereço | Alta | Conversa | RF001 |
| RF007 | Cadastro de categorias | Alta | Conversa | Admin |
| RF008 | Criar solicitação | Alta | Conversa | RF006 |
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

## 7. Regras de Negócio

| ID | Regra |
|---|---|
| RN001 | Apenas profissionais verificados podem receber solicitações. |
| RN002 | Cliente só pode contratar uma proposta por solicitação. |
| RN003 | Pagamento é autorizado no aceite da proposta e só é capturado/repassado após conclusão e aprovação do serviço, não é escrow, é autorizar→capturar→repassar (ver `adr/ADR-002-financeiro.md`, INV-041). Redigido em 2026-08-16 com vocabulário de escrow ("retido"), corrigido em 2026-08-17. |
| RN004 | Avaliação só é permitida com o Serviço em `APROVADO` (não existe estado "concluído" separado, ver `foundation/02-state-machine.md`). |
| RN005 | Todo Serviço que atinge `APROVADO` gera garantia (INV-050). |
| RN006 | Todo Serviço que atinge `APROVADO` entra no prontuário do imóvel via `Intervention` (INV-060). |
| RN007 | Profissionais possuem reputação baseada em desempenho. |
| RN008 | Cancelamentos impactam reputação. |
| RN009 | Fotos podem ser exigidas na conclusão. |
| RN010 | Serviço só pode ser encerrado após confirmação ou prazo de aceite (**Necessita Validação**). |

## 8. Perfis de Usuário

**Visitante**
- Criar conta
- Login

**Cliente**
- Solicitar serviço
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
- Moderar avaliações
- Resolver disputas
- Visualizar métricas
- Suspender contas

## 9. Permissões

| Ação | Cliente | Profissional | Admin |
|---|---|---|---|
| Criar solicitação | ✔ | ✖ | ✔ |
| Enviar proposta | ✖ | ✔ | ✖ |
| Contratar | ✔ | ✖ | ✔ |
| Chat | ✔ | ✔ | ✔ |
| Finalizar serviço | ✖ | ✔ | ✔ |
| Confirmar conclusão | ✔ | ✖ | ✔ |
| Avaliar | ✔ | ✖ | ✔ |
| Gerenciar usuários | ✖ | ✖ | ✔ |

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
- Critérios para evolução dos níveis de reputação.
- Regras para garantia (prazo por categoria, cobertura e exclusões).
- Política de aceite automático caso o cliente não responda.
- Limites de anexos, formatos e tamanho de arquivos.
