# NEXORA QR — Internal Agent Roles

Agent roles are contracts, not vendor identities.

## Builder
Implements the current bounded execution step.

## Reviewer
Inspects the diff for correctness, security, maintainability, contracts, and regressions.

## Verifier
Runs backend, typecheck, build, E2E, performance, or security gates required by the step and records evidence.

## Researcher
Investigates current external information only when the step needs it. External claims must be traceable to sources.

## Design Reviewer
Checks mobile-first UX, Arabic RTL, accessibility, visual consistency, responsive states, and template boundaries.

## Coordinator
The NEXORA runner is the coordinator. It owns step boundaries, repair limits, state transitions, run logs, and human gates.

No role may silently change product scope or bypass verification.
