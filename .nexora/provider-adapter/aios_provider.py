#!/usr/bin/env python3
"""AI OS provider adapter: capability checks and safe, bounded agent dispatch.

Standard-library only. This adapter does not install packages, mutate Git state,
or bypass the selected agent CLI's own permission/sandbox controls.
"""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import uuid


ROOT = Path(__file__).resolve().parents[2]
REGISTRY = ROOT / ".nexora" / "providers" / "providers.json"
LOCAL_CONFIG = ROOT / ".nexora" / "provider-adapter" / "providers.local.json"
REPORT_SCHEMA = ROOT / ".nexora" / "AI-OS-EXECUTION-REPORT.schema.json"
REPORT_DIR = ROOT / ".nexora" / "provider-adapter" / "reports"
QUEUE_PATH = ROOT / ".nexora" / "WORK-QUEUE.json"
TASKS_DIR = ROOT / ".nexora" / "tasks"
DEFAULT_TIMEOUT = 1800
MAX_TIMEOUT = 3600


def now() -> str:
    return dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds")


def load_json(path: Path) -> dict:
    with path.open("r", encoding="utf-8") as stream:
        return json.load(stream)


def command_exists(command: str) -> bool:
    """Check the executable portion without logging the command or its args."""
    if not command.strip():
        return False
    try:
        parts = __import__("shlex").split(command, posix=(os.name != "nt"))
    except ValueError:
        return False
    return bool(parts) and shutil.which(parts[0]) is not None


def configured_command(role: str, spec: dict) -> tuple[str, str]:
    """Environment takes precedence; local non-secret config is a fallback."""
    env_name = spec.get("command_env", "")
    command = os.environ.get(env_name, "") if env_name else ""
    if command.strip():
        return command, "environment"
    if LOCAL_CONFIG.is_file():
        try:
            local = load_json(LOCAL_CONFIG)
            entry = local.get("roles", {}).get(role, {})
            if entry.get("enabled") is True and isinstance(entry.get("command"), str):
                return entry["command"], "local_config"
        except (OSError, ValueError, TypeError):
            pass
    return "", "unconfigured"


def known_git_state() -> dict:
    def git(*args: str) -> str:
        result = subprocess.run(
            ["git", *args], cwd=ROOT, text=True, capture_output=True,
            timeout=10, check=False,
        )
        if result.returncode:
            return "unavailable"
        return result.stdout.strip()

    return {
        "branch": git("branch", "--show-current"),
        "head": git("rev-parse", "--short", "HEAD"),
        "working_tree": "dirty" if git("status", "--porcelain") else "clean",
    }


def working_tree_paths() -> list[str]:
    result = subprocess.run(
        ["git", "status", "--porcelain", "-uno"], cwd=ROOT,
        text=True, capture_output=True, timeout=10, check=False,
    )
    if result.returncode:
        return []
    return [line[3:].strip() for line in result.stdout.splitlines() if len(line) > 3]


def control_plane_snapshot() -> dict[str, str]:
    """Hash only known non-secret orchestration paths to detect edits accurately."""
    files: set[Path] = set()
    fixed = [
        ROOT / ".nexora" / "WORK-QUEUE.json",
        ROOT / ".nexora" / "PROVIDER-CONTRACT.md",
        ROOT / ".nexora" / "AI-OS-ORCHESTRATION.md",
        ROOT / ".nexora" / "AGENT-PROTOCOL.md",
        ROOT / ".github" / "workflows" / "nexora-autonomy-guard.yml",
        ROOT / "scripts" / "nexora-run.sh",
    ]
    files.update(path for path in fixed if path.is_file())
    for directory in (ROOT / ".nexora" / "provider-adapter", ROOT / ".nexora" / "tasks"):
        if directory.is_dir():
            files.update(
                path for path in directory.rglob("*")
                if path.is_file()
                and "reports" not in path.relative_to(directory).parts
                and "__pycache__" not in path.parts
                and path.name != "providers.local.json"
                and path.suffix not in {".pyc", ".tmp"}
            )
    snapshot: dict[str, str] = {}
    for path in sorted(files):
        try:
            digest = hashlib.sha256(path.read_bytes()).hexdigest()
            snapshot[path.relative_to(ROOT).as_posix()] = digest
        except OSError:
            continue
    return snapshot


def registry_status() -> dict:
    registry = load_json(REGISTRY)
    roles = {}
    for role, spec in registry.get("roles", {}).items():
        env_name = spec.get("command_env", "")
        command, source = configured_command(role, spec)
        present = bool(command.strip())
        executable = command_exists(command) if present else False
        roles[role] = {
            "configured": present,
            "executable_found": executable,
            "command_available": present and executable,
            "required_capabilities": spec.get("required_capabilities", []),
            "environment_variable": env_name,
            "configuration_source": source,
        }
    return {
        "adapter": "aios-provider-adapter",
        "version": 1,
        "root": str(ROOT),
        "checked_at": now(),
        "git": known_git_state(),
        "roles": roles,
        "credential_values_logged": False,
    }


def queue_status() -> dict:
    queue = load_json(QUEUE_PATH)
    projects = {item["id"]: item for item in queue.get("projects", [])}
    tasks = queue.get("tasks", [])
    return {
        "queue": str(QUEUE_PATH.relative_to(ROOT)),
        "projects": [
            {
                "id": project_id,
                "observed_status": item.get("observed_status"),
                "next_task": item.get("next_task"),
                "blockers": item.get("blockers", []),
            }
            for project_id, item in projects.items()
        ],
        "tasks": [
            {
                "id": task.get("id"),
                "project": task.get("project"),
                "title": task.get("title"),
                "status": task.get("status"),
                "priority": task.get("priority"),
                "task_file": task.get("task_file"),
                "dependencies": task.get("dependencies", []),
            }
            for task in tasks
        ],
    }


def task_prompt(task_id: str) -> tuple[dict, str]:
    """Resolve a task only from the explicit queue; never arbitrary paths."""
    queue = load_json(QUEUE_PATH)
    tasks = queue.get("tasks", [])
    task = next((item for item in tasks if item.get("id") == task_id), None)
    if task is None:
        raise ValueError(f"Unknown task ID: {task_id}")
    if task.get("status") != "ready":
        raise ValueError(f"Task '{task_id}' is not ready (status={task.get('status')}).")
    project = next((p for p in queue.get("projects", []) if p.get("id") == task.get("project")), None)
    if not project:
        raise ValueError(f"Task '{task_id}' references an unknown project.")
    deps = task.get("dependencies", [])
    completed = {item.get("id") for item in tasks if item.get("status") == "done"}
    unresolved = [dep for dep in deps if dep not in completed]
    if unresolved:
        raise ValueError(f"Task '{task_id}' has unresolved dependencies: {unresolved}")
    task_file = task.get("task_file")
    if not isinstance(task_file, str):
        raise ValueError(f"Task '{task_id}' has no task_file contract.")
    target = (ROOT / task_file).resolve()
    if not target.is_relative_to(TASKS_DIR.resolve()):
        raise ValueError("Task file must resolve inside .nexora/tasks/.")
    if not target.is_file():
        raise ValueError(f"Task file does not exist: {task_file}")
    content = target.read_text(encoding="utf-8")
    project_contract = "\n".join(f"- {path}" for path in project.get("source_of_truth", []))
    prompt = f"""You are the AI OS supervised task executor.

Selected task ID: {task_id}
Project ID: {task.get('project')}
Priority: {task.get('priority', 'unspecified')}
Task contract: {task_file}

Read task contract and these project source-of-truth paths first:
{project_contract}

Task contract content:
--- BEGIN TASK ---
{content}
--- END TASK ---

Mandatory execution rules:
- Work only inside the selected task's allowed scope and repository.
- Inspect branch, commit, status, and the existing diff before edits. Preserve all pre-existing user changes.
- Do not reset, clean, discard, stage, commit, push, merge, deploy, publish, purchase, or create paid resources.
- Do not read secret/credential file contents or print environment values.
- Do not claim checks passed unless you ran them and observed their exit status.
- If scope/policy/access is blocked, do not guess: report last successful step and next safe action.
- Run the acceptance checks defined in the task contract.
- Respond with Done, Changed, Tested, Verified, Remaining Issues, and Next Step.
"""
    return task, prompt


def next_ready_task(project_id: str | None = None) -> dict:
    queue = load_json(QUEUE_PATH)
    projects = {p.get("id"): p for p in queue.get("projects", [])}
    tasks = [t for t in queue.get("tasks", []) if t.get("status") == "ready"]
    if project_id:
        tasks = [t for t in tasks if t.get("project") == project_id]
    priority_order = {"P0": 0, "P1": 1, "P2": 2, "P3": 3}
    tasks.sort(key=lambda t: (priority_order.get(t.get("priority"), 9), t.get("id", "")))
    for task in tasks:
        if task.get("project") not in projects:
            continue
        try:
            task_prompt(task["id"])
            return task
        except (OSError, ValueError, KeyError, TypeError):
            continue
    raise ValueError("No eligible ready task exists for the requested project.")


def normalize_command(command: str, prompt: str) -> list[str]:
    """Support an executable plus fixed args; prompt is passed as stdin."""
    import shlex

    parts = shlex.split(command, posix=(os.name != "nt"))
    if not parts:
        raise ValueError("empty command")
    # Codex and Claude expose explicit non-interactive entry points. Keep the
    # selected CLI responsible for permissions and its normal sandbox.
    name = Path(parts[0]).name.lower()
    if name in {"codex", "codex.exe", "codex.cmd", "codex.ps1"}:
        return [*parts, "exec", "--json", "--sandbox", "workspace-write", "-"]
    if name in {"claude", "claude.exe", "claude.cmd", "claude.ps1"}:
        return [*parts, "--print", "--output-format", "json", "--permission-mode", "acceptEdits"]
    # Generic providers may explicitly declare stdin support. The prompt isn't
    # interpolated into shell syntax; shell execution is never used.
    return parts


def execute(args: argparse.Namespace) -> int:
    start = now()
    run_id = str(uuid.uuid4())
    registry = load_json(REGISTRY)
    role_spec = registry.get("roles", {}).get(args.role)
    if not role_spec:
        print(f"Unknown provider role: {args.role}", file=sys.stderr)
        return 2
    env_name = role_spec.get("command_env", "")
    command, _source = configured_command(args.role, role_spec)
    if not command.strip() or not command_exists(command):
        print(f"BLOCKED: provider role '{args.role}' is not configured with an available executable ({env_name}).")
        return 2
    if args.timeout < 1 or args.timeout > MAX_TIMEOUT:
        print(f"Timeout must be between 1 and {MAX_TIMEOUT} seconds.", file=sys.stderr)
        return 2

    prompt = sys.stdin.read()
    if not prompt.strip():
        print("Prompt stdin is empty.", file=sys.stderr)
        return 2
    # Never permit control-plane tasks to request the dangerous bypass flags.
    command_args = normalize_command(command, prompt)
    if any("dangerously-bypass" in arg.lower() for arg in command_args):
        print("Blocked unsafe provider argument.", file=sys.stderr)
        return 2

    report = {
        "run_id": run_id,
        "project": args.project,
        "task_id": args.task_id,
        "status": "in_progress",
        "started_at": start,
        "finished_at": start,
        "summary": "Provider invocation started.",
        "changed_files": [],
        "checks": [],
        "blockers": [],
        "next_action": "Inspect provider output and verify task acceptance criteria.",
        "commit": None,
        "pull_request": None,
        "approval_required": ["commit", "push", "merge", "production deploy", "external publication"],
    }
    git_before = known_git_state()
    snapshot_before = control_plane_snapshot()
    try:
        result = subprocess.run(
            command_args,
            cwd=ROOT,
            input=prompt,
            text=True,
            encoding="utf-8",
            errors="replace",
            capture_output=True,
            timeout=args.timeout,
            check=False,
            shell=False,
        )
        report["checks"].append({
            "name": "provider_invocation",
            "status": "passed" if result.returncode == 0 else "failed",
            "command": Path(command_args[0]).name + " (arguments omitted)",
            "evidence": f"exit_code={result.returncode}; stdout_chars={len(result.stdout)}; stderr_chars={len(result.stderr)}",
        })
        # Print result payload for the caller. It is the provider's normal output,
        # not assumed proof that task verification passed.
        print(result.stdout, end="" if result.stdout.endswith("\n") or not result.stdout else "\n")
        if result.stderr.strip():
            print("[provider stderr captured; contents omitted from control log]", file=sys.stderr)
        report["status"] = "needs_review" if result.returncode == 0 else "failed"
        report["summary"] = "Provider returned; task completion requires independent acceptance checks."
        report["blockers"] = [] if result.returncode == 0 else [f"Provider exited with code {result.returncode}; diagnostics must be reviewed without exposing secrets."]
        report["next_action"] = "Run task-specific verification and inspect the diff before marking Done." if result.returncode == 0 else "Diagnose the failure, respect retry limits, and retain existing work."
        return_code = result.returncode
    except subprocess.TimeoutExpired:
        report["status"] = "blocked"
        report["summary"] = "Provider invocation timed out."
        report["blockers"] = [f"Provider timed out after {args.timeout}s; no automatic retry was started."]
        report["next_action"] = "Inspect the process/worktree, then decide whether a bounded retry is safe."
        report["checks"].append({"name": "provider_invocation", "status": "failed", "command": Path(command_args[0]).name, "evidence": f"timeout={args.timeout}s"})
        return_code = 124
    except (OSError, UnicodeError, subprocess.SubprocessError) as exc:
        report["status"] = "failed"
        report["summary"] = "Provider could not be invoked successfully."
        report["blockers"] = [f"{type(exc).__name__}: provider process setup or I/O failed; inspect the local environment and retry after a safe fix."]
        report["next_action"] = "Fix the process/encoding issue, then rerun this still-uncompleted queue task."
        report["checks"].append({"name": "provider_invocation", "status": "failed", "command": Path(command_args[0]).name, "evidence": f"exception={type(exc).__name__}"})
        return_code = 1
    finally:
        report["finished_at"] = now()
        before = git_before
        after = known_git_state()
        # Only report newly dirty paths. The baseline can already be dirty.
        snapshot_after = control_plane_snapshot()
        report["changed_files"] = sorted(
            path for path in set(snapshot_before) | set(snapshot_after)
            if snapshot_before.get(path) != snapshot_after.get(path)
        )
        report["checks"].append({"name": "git_state_snapshot", "status": "passed", "evidence": json.dumps({"before": before, "after": after}, ensure_ascii=False)})
        REPORT_DIR.mkdir(parents=True, exist_ok=True)
        target = REPORT_DIR / f"{run_id}.json"
        temp = target.with_suffix(".tmp")
        temp.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        temp.replace(target)
        print(f"\nAI OS report: {target.relative_to(ROOT)}", file=sys.stderr)
    return return_code


def main() -> int:
    parser = argparse.ArgumentParser(description="AI OS provider adapter")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("status", help="Show configured provider readiness without secret values")
    sub.add_parser("queue", help="List projects and task readiness")
    next_task = sub.add_parser("next", help="Resolve the next eligible ready task without executing it")
    next_task.add_argument("--project")
    run = sub.add_parser("run", help="Run one bounded task using a configured provider; prompt is read from stdin")
    run.add_argument("--role", default="coding-primary")
    run.add_argument("--project")
    run.add_argument("--task-id", required=True)
    run.add_argument("--timeout", type=int, default=DEFAULT_TIMEOUT)
    args = parser.parse_args()
    if args.command == "status":
        print(json.dumps(registry_status(), indent=2, ensure_ascii=False))
        return 0
    if args.command == "queue":
        print(json.dumps(queue_status(), indent=2, ensure_ascii=False))
        return 0
    if args.command == "next":
        try:
            print(json.dumps(next_ready_task(args.project), indent=2, ensure_ascii=False))
            return 0
        except (OSError, ValueError, KeyError, TypeError) as exc:
            print(f"BLOCKED: {exc}", file=sys.stderr)
            return 2
    try:
        task, generated_prompt = task_prompt(args.task_id)
    except (OSError, ValueError, KeyError, TypeError) as exc:
        print(f"BLOCKED: {exc}", file=sys.stderr)
        return 2
    if args.project and args.project != task.get("project"):
        print("BLOCKED: --project does not match the queued task's project.", file=sys.stderr)
        return 2
    args.project = task["project"]
    # Queue tasks own their prompt. Interactive/general stdin is not allowed to
    # override the task contract and unexpectedly expand scope.
    sys.stdin = __import__("io").StringIO(generated_prompt)
    return execute(args)


if __name__ == "__main__":
    raise SystemExit(main())
