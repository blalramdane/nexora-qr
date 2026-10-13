# AI OS Provider Adapter

Standard-library-only first adapter for the live NEXORA QR workspace. The adapter reuses installed coding-agent CLIs rather than implementing or hosting a new model.

## Inspect readiness

From the repository root:

```powershell
python .nexora/provider-adapter/aios_provider.py status
```

Reports which configured provider-role commands are present, without printing command values or credential contents. Environment variables take precedence over the ignored machine-local `providers.local.json` file. The current local default selects `codex.cmd` for the primary coding role; this confirms command selection only, not an end-to-end agent run.

## Invoke one supervised task

Only after a role is configured in the host environment and its workspace permissions are understood:

```powershell
Get-Content .nexora/tasks/example.md -Raw | python .nexora/provider-adapter/aios_provider.py run --role coding-primary --project nexora-qr --task-id NQR-001
```

Queue-native invocation (recommended; task prompt is loaded from the queue's `task_file`, so arbitrary stdin cannot override the task scope):

```powershell
python .nexora/provider-adapter/aios_provider.py queue
python .nexora/provider-adapter/aios_provider.py next --project ai-os
python .nexora/provider-adapter/aios_provider.py run --task-id AIOS-PROVIDER-SUPERVISED-PILOT
```

The repository runner's `auto` mode selects the next eligible P0/P1/P2/P3 task in the `ai-os` project. Pass an explicit task ID to run a different queued task. `run` accepts only tasks marked `ready` with resolved dependencies and a task file contained inside `.nexora/tasks/`.

The runner accepts a prompt from stdin, does not use a shell for the provider process, limits timeout to 3600 seconds, captures exit evidence and writes a JSON report under `.nexora/provider-adapter/reports/`. A clean provider exit is `needs_review`, not `done`. `changed_files` lists newly dirty paths relative to the pre-run snapshot; pre-existing dirty paths are preserved and are not attributed to this invocation.

## Provider command environment

The registry's `command_env` values define the environment variables. The adapter does not persist credentials or command values. It does not auto-configure a provider or infer that a login implies permission for unattended edits.

### Codex CLI

The adapter uses the CLI's `codex exec --json --sandbox workspace-write -` non-interactive entry point when the configured executable is Codex. It deliberately avoids full-access / approvals-bypass flags.

### Claude Code

The adapter uses Claude's non-interactive print mode with `acceptEdits` when Claude is configured. Configure only if the account/auth and permission policies allow it. No `bypassPermissions` or dangerous skip-permission mode is enabled here.

## Safeguards and limits

- Does not install packages, modify Git staging, create commits, push, merge, deploy, or publish.
- Does not claim completion solely from the agent's final text.
- Does not log provider command strings, environment variable values, or captured stderr.
- Stores a JSON execution report; review workspace diffs and run task-specific checks separately.
- The local run report directory must remain excluded from source control if reports can include project paths/context.
- Persistent scheduled runs are intentionally out of scope for the initial adapter until one supervised cycle is validated.
