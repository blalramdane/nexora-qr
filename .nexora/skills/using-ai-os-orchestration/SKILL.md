---
name: using-ai-os-orchestration
description: Use when coordinating work across multiple NEXORA projects, selecting the next task, reporting progress, or deciding whether work can be delegated safely.
---

# AI OS Orchestration

## First, establish the truth

1. Read `.nexora/AI-OS-ORCHESTRATION.md` and `.nexora/WORK-QUEUE.json`.
2. Read each affected project's source-of-truth, status, execution plan, and task-loop files.
3. Inspect current branch/status and recent commits before edits. Preserve existing working-tree changes.
4. Distinguish repository-documented history from fresh verification in this run.

## Pick the next task

1. Select the highest-priority `ready` task whose dependencies are complete.
2. Do not start a task with missing acceptance criteria, unavailable capabilities, unsafe data access, or unresolved owner-only policy.
3. Parallelize only independent read-only work. Serialize shared-code edits unless isolation has been proved.
4. Confirm an available executor can read, edit, run checks, inspect failures, and return structured evidence before dispatch.

## Execute and verify

Requirement → Evidence → Impact map → Minimal change → Focused test → Broader checks as required → Review → State update.

For failures: Reproduce → Evidence → Root cause → Smallest safe repair → Test → Verify. Respect the project's repair-attempt cap.

## Completion standard

Never mark `done` because code exists. Require acceptance criteria, actual checks and results, relevant diff review, documented limitations and an execution report. Keep skipped tests distinct from passed tests.

## Safety

- Preserve existing work; do not reset, clean, discard, stage, commit, push, merge, deploy or publish without appropriate authorization.
- Do not inspect or emit secrets. Skip credential files and secret-bearing environment/config values.
- Keep production, financial policy, tenant isolation, and inventory/accounting decisions behind explicit safeguards.
- Do not install a third-party skill or run its scripts without reviewing provenance, license, compatibility, permissions and side effects.
- If blocked, write the last successful step, exact blocker and next safe action, then continue independent ready work.

## Report

Use `Done`, `Changed`, `Tested`, `Verified`, `Remaining Issues`, and `Next Step`. Include actual evidence paths/commands and state whether a result was fresh or historical.
