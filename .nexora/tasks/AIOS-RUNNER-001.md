# AIOS-RUNNER-001 — Make queue execution resumable and concurrency-safe

Status: READY  
Project: `ai-os`  
Priority: P0

## Objective

Harden the AI OS queue runner so an invocation is exclusive, its selected task has an explicit lifecycle, and failures can be resumed without falsely marking work complete.

## Allowed scope

- `.nexora/provider-adapter/`
- `.nexora/WORK-QUEUE.json`
- `.nexora/PROVIDER-CONTRACT.md`
- `.nexora/AI-OS-ORCHESTRATION.md`
- `.nexora/AGENT-PROTOCOL.md`
- `.nexora/tasks/AIOS-RUNNER-001.md`
- `.github/workflows/nexora-autonomy-guard.yml`
- `scripts/nexora-run.sh`

## Acceptance criteria

1. Prevent two adapter invocations from mutating the queue simultaneously; fail clearly if a live run already owns the lock.
2. Record task lifecycle (`ready` → `in_progress` → `needs_review`, or `failed`/`blocked`) with atomic JSON writes and preserve unrelated queue entries.
3. A successful provider exit must never automatically mark the task `done`.
4. Preserve bounded timeout, Codex sandboxing, and the existing machine-local provider path.
5. Add deterministic tests for lock contention, task state transitions, and queue integrity.
6. Run unit tests, queue/schema JSON checks, runner syntax, dry-run, and `git diff --check`.

## Forbidden actions

No reset/clean/discard, staging, commit, push, merge, deploy, publication, dependency installation, secret inspection, or application/product edits.

## Final report

List changed files, tests with exit codes, remaining risks, and the exact next eligible task. Keep this task `needs_review` until the coordinator reviews the evidence.
