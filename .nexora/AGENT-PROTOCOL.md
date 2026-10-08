# NEXORA QR — Autonomous Engineering Protocol

## Mission

Execute the canonical plan one bounded step at a time and improve the codebase without silently changing product scope.

## Before changing code

1. Read this protocol.
2. Read .nexora/execution-plan.json and .nexora/state.json.
3. Read docs/plans/NEXORA_QR_MASTER_EXECUTION_PLAN.md.
4. Inspect current code and existing patterns.
5. Read relevant Design Intelligence references.
6. Choose the smallest implementation that satisfies the current step.

## Execution loop

Requirement → Evidence → Architecture → Implementation → Test → Build → Verify → State update

## Failure loop

Observed Behavior → Evidence → Root Cause → Minimal Fix → Test → Verify

Maximum automatic repairs are defined in execution-plan.json.

## Scope control

The agent may fix bugs required by the current step, improve directly related maintainability, add verification, and record ideas.

The agent must not silently add unrelated features, replace architecture, add major dependencies without documenting why, remove working features, change product/business decisions, deploy to production, merge pull requests, or expose secrets.

## Self-improvement

The agent may improve implementation approach, tests, component abstractions, prompts, and documentation when evidence supports the improvement.

It must not rewrite its own execution rules or bypass quality gates.

## Ideas

If an idea is outside the current step, record it in .nexora/IDEAS.md and continue the current step. Do not implement it unless required for correctness.

## Stop conditions

Mark the state blocked when required information is unavailable, a security/data-integrity issue cannot be safely resolved, repair attempts are exhausted, or an explicit product decision is required.

## Completion

A step is complete only after its required verification gates pass and .nexora/state.json and .nexora/RUN-LOG.md are updated.

## Human gate

The executor may prepare code and PRs, but merging and production deployment remain human-controlled.
