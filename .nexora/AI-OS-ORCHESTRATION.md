# AI OS — Supervised Orchestration Contract

**Status:** Initial implementation baseline; persistent execution is not enabled by this document alone.

## Roles

- **ChatGPT / Coordinator:** identifies scope, reads source-of-truth documents, ranks tasks and reviews evidence.
- **Chat On Steroids / Executor:** inspects the actual workspace, edits bounded tasks, executes project checks and preserves the working tree.
- **GitHub / Source-control evidence:** reads repository, branch, commit, PR and CI state when authorized and connected.
- **Independent reviewer:** checks task scope, regressions, tenant/data integrity and acceptance criteria.
- **Verifier:** runs declared test/build/security gates and reports actual outcomes.

Roles are capability-based. A name in configuration is not evidence that a provider exists. Verify capabilities before dispatch.

## Source-of-truth order

1. Latest explicit user decision.
2. Project-specific source-of-truth / architecture / policy documents.
3. Project execution plan and state, reconciled with current working tree and recent commits.
4. Tests, builds, CI and runtime behavior for the exact code under review.
5. Research or agent inference, clearly labeled as such.

If state files conflict with the observed code or results, do not silently choose one; create a reconciliation task.

## Dispatcher contract

1. Discover accessible workspace roots and project manifests.
2. Skip dependencies, build output, `.git`, and credential/config files that may contain secrets. Never print secret values.
3. For each project, read the documented source of truth and inspect branch/status before edits.
4. Normalize work into `.nexora/WORK-QUEUE.json` task records with IDs, priority, dependencies, status, acceptance criteria, verification commands, blockers, and owner/tool.
5. Execute only `ready` tasks whose dependencies are complete and whose access/capability requirements are satisfied.
6. Permit parallelism for independent read-only audits. Serialize code edits per repository unless isolated branches/worktrees are proven safe.
7. Apply minimal changes. Never discard pre-existing changes, overwrite unrelated files, or add unrelated features.
8. Run the checks declared by the task. A `DONE` state requires successful acceptance evidence and a reviewable diff.
9. Write a schema-conforming execution report and update queue/project state atomically where feasible.
10. On a blocker, record the exact last successful step and next safe action, then continue independent ready tasks.

## Status definitions

- `inbox`: found but not triaged.
- `ready`: scoped, dependencies met, checks defined.
- `in_progress`: being implemented or checked.
- `needs_review`: checks ran; independent review is pending.
- `blocked-pending-evidence`: requires owner decision, access, environment, or external result.
- `failed`: acceptance check failed and bounded repair is exhausted or unsafe.
- `done`: acceptance criteria and required verification passed with concrete evidence.
- `deferred`: intentionally postponed with a reason.

## Safety gates

- Never reset/clean/discard, stage, commit, push, merge, deploy or publish without the authorization required by project instructions.
- Never run migrations against production or import live customer/business data without explicit approval.
- Never send external messages, buy services, incur costs or create paid resources autonomously.
- Do not bypass authentication, authorization, tenant isolation, accounting or inventory invariants to make a test pass.
- Do not execute third-party downloaded scripts blindly. Inspect license, provenance, installation path, required permissions and side effects first.
- GitHub issue/PR creation, remote file writes, merges and workflow retries are remote mutations; use only when explicitly in scope and authorized.
- Cap automatic repair attempts at the project-defined limit. Do not loop forever.

## GitHub Skill Intake

Skills from external repositories are candidates, not trusted executable code. Review README, license, skill instructions and any scripts first. Prefer adopting small, portable process guidance into local project-specific skills instead of adding another runtime or package manager.

Initial candidates reviewed:

- `obra/superpowers`: workflow concepts for bounded planning, TDD, review and subagent task execution. Adopt concepts selectively; installation instructions vary by harness and must not be assumed to work in Chat On Steroids.
- `mattpocock/skills`: modular engineering practices and test-first/review workflows. Use selected instructions as references; do not install duplicate skill packs globally.
- `anthropics/skills`: examples and specification references for skills. Its repository README explicitly warns that examples are for demonstration/education and should be tested before relying on them for critical tasks.

No external package/skill was installed as part of this baseline. This avoids adding duplicate or harness-incompatible tooling before provider compatibility is proven.

## Persistent execution requirement

Continuous execution requires an actual persistent runner/scheduler/CI workflow with credentials and permissions configured in its host environment. A prompt, chat message, skill, or plan file cannot independently run between conversations. First validate one complete supervised task cycle, then add a bounded scheduler only when provider capabilities and interruption/recovery behavior are confirmed.

## End-of-run report

Every run must report: `Done`, `Changed`, `Tested`, `Verified`, `Remaining Issues`, `Next Step`, and concrete evidence. Distinguish historical test results from tests executed on the current working tree.
