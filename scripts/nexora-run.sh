#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLAN="$ROOT/.nexora/execution-plan.json"
STATE="$ROOT/.nexora/state.json"
PROTOCOL="$ROOT/.nexora/AGENT-PROTOCOL.md"

for f in "$PLAN" "$STATE" "$PROTOCOL"; do
  [[ -f "$f" ]] || { echo "NEXORA runner: missing $f" >&2; exit 1; }
done

STEP="${1:-auto}"
MAX_STEPS="${NEXORA_MAX_STEPS:-1}"
DRY_RUN="${NEXORA_DRY_RUN:-0}"

echo "NEXORA Autonomous Executor"
echo "Requested step: $STEP"
echo "Max steps: $MAX_STEPS"

if [[ "$DRY_RUN" == "1" ]]; then
  echo "DRY RUN: control files are present."
  exit 0
fi

if [[ -z "${NEXORA_AGENT_CMD:-}" ]]; then
  echo "NEXORA_AGENT_CMD is required."
  echo "Set it to the approved coding-agent CLI/command used by your environment."
  exit 2
fi

PROMPT=$(cat <<EOF
You are the NEXORA QR autonomous engineering agent.

Repository root: $ROOT

Read first:
- .nexora/AGENT-PROTOCOL.md
- .nexora/execution-plan.json
- .nexora/state.json
- docs/plans/NEXORA_QR_MASTER_EXECUTION_PLAN.md
- docs/design-intelligence/README.md
- docs/design-intelligence/AGENT-SKILLS-2026.md

Execute the next bounded step only unless the state is blocked.
Requested step: $STEP
Maximum steps: $MAX_STEPS

Modify source code, tests and docs required by the current step.
Run every required verification gate.
If something fails, diagnose evidence and repair within the configured limit.
Do not merge, deploy, expose secrets, or silently expand scope.
Record out-of-scope ideas in .nexora/IDEAS.md.
Update .nexora/state.json and .nexora/RUN-LOG.md.
Report step, changes, tests, build, verification, blockers, ideas, and remaining work.
EOF
)

printf '%s\n' "$PROMPT" | bash -lc "$NEXORA_AGENT_CMD"
