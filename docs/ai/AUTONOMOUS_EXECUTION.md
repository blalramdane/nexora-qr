# NEXORA QR — Autonomous Execution

## What this system does

The autonomous executor is a supervised engineering loop around the canonical NEXORA QR plan.

It can:
- select the next bounded task
- inspect the existing code
- implement the task
- add/update tests
- run verification
- diagnose failures
- repair implementation within a limited retry budget
- update execution state
- record decisions
- capture out-of-scope ideas
- produce a final report when the full plan is complete

It is intentionally not a production deployment bot.

## Control files

- .nexora/execution-plan.json — machine-readable roadmap and gates
- .nexora/state.json — current execution state
- .nexora/AGENT-PROTOCOL.md — operating rules
- .nexora/IDEAS.md — deferred ideas
- .nexora/DECISIONS.md — important decisions
- .nexora/RUN-LOG.md — execution history
- .nexora/FINAL-REPORT.md — final project report
- docs/plans/NEXORA_QR_MASTER_EXECUTION_PLAN.md — canonical human-readable plan
- scripts/nexora-run.sh — execution entrypoint

## Run model

The runner delegates actual coding to an approved coding-agent command supplied by NEXORA_AGENT_CMD.

Example:

NEXORA_AGENT_CMD='YOUR_APPROVED_AGENT_COMMAND' bash scripts/nexora-run.sh auto

Dry run:

NEXORA_DRY_RUN=1 bash scripts/nexora-run.sh auto

Limit one bounded step:

NEXORA_MAX_STEPS=1 bash scripts/nexora-run.sh auto

## Why the agent command is external

The repository should contain the orchestration contract, not credentials or a hard-coded vendor API key.

This keeps the project portable across coding agents and local/CI environments.

## Safety model

Automatic:
- implementation
- tests
- repairs
- documentation
- state updates
- idea capture

Human-controlled:
- product decisions
- merge
- production deployment
- credentials
- destructive infrastructure changes

## Self-improvement boundary

The agent may improve code, tests, abstractions, performance, documentation and its implementation approach when evidence supports it.

It cannot rewrite the execution rules to bypass verification or silently expand scope.

## Completion

When all steps are verified:
1. mark the plan complete
2. summarize all changes
3. summarize tests and verification
4. summarize deferred ideas
5. list remaining issues
6. update .nexora/FINAL-REPORT.md
7. stop and wait for human review
