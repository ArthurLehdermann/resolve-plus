#!/usr/bin/env bash
set -uo pipefail
cd "$(dirname "$0")/.."
DOCS=docs
FAIL=0

report() { echo "[$1] $2"; FAIL=1; }

INV_DEF=$(grep -o "^- INV-[0-9]\{3\}" $DOCS/foundation/00-domain-invariants.md | grep -o "INV-[0-9]\{3\}" | sort -u)
for r in $(grep -rho "INV-[0-9]\{3\}" $DOCS | sort -u); do
  grep -qx "$r" <<<"$INV_DEF" || report REF "$r referenciada, nao definida em 00-domain-invariants.md"
done

B_DEF=$(grep -o "^## B[0-9]\{3\}" $DOCS/foundation/04-decisions-pending.md | grep -o "B[0-9]\{3\}" | sort -u)
for r in $(grep -rho "\bB[0-9]\{3\}\b" $DOCS | sort -u); do
  grep -qx "$r" <<<"$B_DEF" || report REF "$r referenciado, nao definido em 04-decisions-pending.md"
done

RF_DEF=$(grep -o "^| RF[0-9]\{3\}" $DOCS/specifications/02-funcionalidades.md | grep -o "RF[0-9]\{3\}" | sort -u)
for r in $(grep -rho "\bRF[0-9]\{3\}\b" $DOCS | sort -u); do
  grep -qx "$r" <<<"$RF_DEF" || report REF "$r referenciado, nao definido"
done

RN_DEF=$(grep -o "^| RN[0-9]\{3\}" $DOCS/specifications/02-funcionalidades.md | grep -o "RN[0-9]\{3\}" | sort -u)
for r in $(grep -rho "\bRN[0-9]\{3\}\b" $DOCS | sort -u); do
  grep -qx "$r" <<<"$RN_DEF" || report REF "$r referenciado, nao definido"
done

LEGACY="CONCLUIDO FINALIZADO Contratacao HistoricoImovel RETIDO LIBERADO"
for t in $LEGACY; do
  hits=$(grep -rn "\b$t\b" $DOCS | grep -vi "Corrigido em\|removid\|Substitu\|versao anterior\|versão anterior\|nao existe\|não existe\|Changelog\|historico\|histórico\|^\S*:[0-9]*:|" || true)
  [ -n "$hits" ] && report LEGACY "token obsoleto '$t':
$hits"
done

STATES_SERVICO="AGENDADO EM_ANDAMENTO AGUARDANDO_APROVACAO APROVADO EM_CONTESTACAO CANCELADO"
for s in $(grep -o "StatusServico\*\*: .*" $DOCS/specifications/04-modelo-dados.md | grep -o "\`[A-Z_]*\`" | tr -d '`'); do
  grep -qw "$s" <<<"$STATES_SERVICO" || report ENUM "StatusServico contem '$s' fora do conjunto esperado"
done

AUTH_ENUM=$(grep -o "StatusPaymentAuthorization\*\*: .*" $DOCS/specifications/04-modelo-dados.md | grep -o "\`[A-Z_]*\`" | tr -d '`' | tr '\n' ' ')
# So conta como "tratado como status" se aparecer numa linha de transicao (-->) dentro de bloco
# de codigo, sem a palavra "evento" qualificando (evento e' PaymentEvent.tipo, nao status).
SM_CODE=$(awk '/^```/{c=!c; next} c' $DOCS/foundation/02-state-machine.md)
for s in Repassado Reembolsado Reautorizado; do
  u=$(tr '[:lower:]' '[:upper:]' <<<"$s")
  bad=$(grep -E -- '-->' <<<"$SM_CODE" | grep -E "\b($s|$u)\b" | grep -viE "evento")
  if [ -n "$bad" ]; then
    grep -qw "$u" <<<"$AUTH_ENUM" || report ENUM "state machine trata '$s' como status de PaymentAuthorization (arrow sem 'evento'), enum nao tem ($AUTH_ENUM): $bad"
  fi
done

grep -rn "|, |" $DOCS >/dev/null && report SED "celula de tabela virou '|, |' apos substituicao de travessao"
grep -rn "^# [0-9]\{2\}, " $DOCS >/dev/null && report SED "titulo H1 no formato '# NN, Titulo'"

# enum -> state machine: todo valor de TipoPaymentEvent citado em 02-state-machine
for e in $(grep -o "TipoPaymentEvent\*\*: .*" $DOCS/specifications/04-modelo-dados.md \
           | grep -o "\`[A-Z_]*\`" | tr -d '`'); do
  grep -q "$e" $DOCS/foundation/02-state-machine.md \
    || report ENUM "$e no enum, ausente de 02-state-machine.md"
done

# invariante orfa: definida em 00 e nunca referenciada fora
for i in $(grep -o "^- INV-[0-9]\{3\}" $DOCS/foundation/00-domain-invariants.md \
           | grep -o "INV-[0-9]\{3\}"); do
  n=$(grep -rl "$i" $DOCS | grep -vc "00-domain-invariants")
  [ "$n" -eq 0 ] && report ORFA "$i definida, nunca aplicada em nenhum doc downstream"
done

grep -rq "—" $DOCS && report SED "travessao reintroduzido"

[ $FAIL -eq 0 ] && echo "ok" || echo "FALHOU"
exit $FAIL
