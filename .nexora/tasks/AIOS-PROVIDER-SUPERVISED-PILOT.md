# AIOS-PROVIDER-SUPERVISED-PILOT

Status: READY FOR SUPERVISED EXECUTION  
Project: `ai-os`  
Priority: P0  
Provider: `coding-primary` (machine-local Codex CLI configuration)  
Risk: Low if the agent stays on these control-plane files only.

## Objective

Validate that the configured Provider can take a bounded task, edit only its allowed control-plane files, run checks, and return evidence through the AI OS adapter.

## Allowed scope

- `.nexora/provider-adapter/`
- `.nexora/WORK-QUEUE.json`
- `.nexora/PROVIDER-CONTRACT.md`
- `.nexora/AI-OS-ORCHESTRATION.md`
- `.nexora/AGENT-PROTOCOL.md`
- `.github/workflows/nexora-autonomy-guard.yml`
- `scripts/nexora-run.sh`

Do not edit application/product files in this pilot. Do not inspect secret contents.

## Acceptance criteria

1. Read the current adapter, registry, task queue, and orchestration protocol.
2. Add or improve one deterministic test for queue/adapter integration if a concrete gap is found.
3. Run the adapter offline unit tests, JSON/queue integrity checks, Bash syntax and runner dry-run.
4. Report exact commands, exit codes, changed paths and any gap not fixed.
5. The execution report remains `needs_review`; only the coordinator may mark this task `done` after reviewing the diff and test evidence.

## Forbidden actions

No reset/clean/discard, staging, commit, push, PR, merge, deployment, publication, dependency installation, paid resources, production access, or changes to business policy.

## Required final response

Use `Done`, `Changed`, `Tested`, `Verified`, `Remaining Issues`, `Next Step`. Do not claim application tests passed unless they were run.
