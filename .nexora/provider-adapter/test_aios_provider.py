"""Offline tests for the AI OS provider adapter; no provider invocation occurs."""

import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch


MODULE_PATH = Path(__file__).with_name("aios_provider.py")
SPEC = importlib.util.spec_from_file_location("aios_provider", MODULE_PATH)
provider = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(provider)


class ProviderAdapterTests(unittest.TestCase):
    def test_codex_command_is_non_interactive_and_sandboxed(self):
        args = provider.normalize_command("codex.cmd", "not passed in argv")
        self.assertEqual(args[0], "codex.cmd")
        self.assertIn("exec", args)
        self.assertIn("--sandbox", args)
        self.assertIn("workspace-write", args)
        self.assertEqual(args[-1], "-")
        self.assertFalse(any("dangerously-bypass" in arg for arg in args))

    def test_claude_command_uses_accept_edits_not_bypass(self):
        args = provider.normalize_command("claude.cmd", "not passed in argv")
        self.assertIn("--print", args)
        self.assertIn("acceptEdits", args)
        self.assertFalse(any("bypass" in arg.lower() for arg in args))

    def test_unknown_provider_command_is_not_wrapped_in_shell(self):
        args = provider.normalize_command("custom-agent --safe", "prompt")
        self.assertEqual(args, ["custom-agent", "--safe"])

    def test_status_does_not_expose_command_or_secret_values(self):
        with patch.dict(os.environ, {"NEXORA_AGENT_CMD": "codex.cmd", "GROQ_API_KEY": "never-print-this"}, clear=True), patch.object(provider.shutil, "which", return_value="C:/fake/codex.cmd"), patch.object(provider, "known_git_state", return_value={"branch": "test", "head": "abc123", "working_tree": "clean"}):
            status = provider.registry_status()
        rendered = __import__("json").dumps(status)
        self.assertNotIn("never-print-this", rendered)
        self.assertNotIn("codex.cmd", rendered)
        self.assertTrue(status["roles"]["coding-primary"]["command_available"])
        self.assertFalse(status["credential_values_logged"])

    def test_missing_provider_fails_closed_before_reading_prompt(self):
        class Args:
            role = "coding-primary"
            timeout = 60
            project = "ai-os"
            task_id = "unit-test"

        missing_config = MODULE_PATH.parent / "missing.providers.json"
        with patch.dict(os.environ, {}, clear=True), patch.object(provider, "LOCAL_CONFIG", missing_config), patch("sys.stdin.read", side_effect=AssertionError("stdin must not be read")):
            self.assertEqual(provider.execute(Args()), 2)

    def test_local_provider_config_is_fallback_and_environment_wins(self):
        with patch.object(provider, "LOCAL_CONFIG", MODULE_PATH.parent / "providers.local.json"):
            command, source = provider.configured_command("coding-primary", {"command_env": "NEXORA_AGENT_CMD"})
            self.assertEqual(command, r"C:\Users\lab\AppData\Roaming\npm\codex.cmd")
            self.assertEqual(source, "local_config")
            with patch.dict(os.environ, {"NEXORA_AGENT_CMD": "claude.cmd"}, clear=True):
                command, source = provider.configured_command("coding-primary", {"command_env": "NEXORA_AGENT_CMD"})
                self.assertEqual(command, "claude.cmd")
                self.assertEqual(source, "environment")

    def test_timeout_is_bounded(self):
        self.assertLessEqual(provider.MAX_TIMEOUT, 3600)
        self.assertGreater(provider.DEFAULT_TIMEOUT, 0)

    def test_ready_queue_task_resolves_to_bounded_task_prompt(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            root = Path(temp_dir)
            tasks_dir = root / ".nexora" / "tasks"
            tasks_dir.mkdir(parents=True)
            task_file = tasks_dir / "AIOS-PROVIDER-SUPERVISED-PILOT.md"
            task_file.write_text("# Pilot\n\nStay inside the allowed scope.\n", encoding="utf-8")
            queue_path = root / ".nexora" / "WORK-QUEUE.json"
            queue_path.write_text(
                json.dumps(
                    {
                        "projects": [
                            {
                                "id": "ai-os",
                                "source_of_truth": [
                                    ".nexora/AI-OS-ORCHESTRATION.md",
                                    ".nexora/WORK-QUEUE.json",
                                ],
                            }
                        ],
                        "tasks": [
                            {
                                "id": "AIOS-PROVIDER-SUPERVISED-PILOT",
                                "project": "ai-os",
                                "status": "ready",
                                "priority": "P0",
                                "task_file": ".nexora/tasks/AIOS-PROVIDER-SUPERVISED-PILOT.md",
                                "dependencies": [],
                            }
                        ],
                    }
                ),
                encoding="utf-8",
            )

            with patch.object(provider, "ROOT", root), patch.object(provider, "QUEUE_PATH", queue_path), patch.object(provider, "TASKS_DIR", tasks_dir):
                task = provider.next_ready_task("ai-os")
                resolved_task, prompt = provider.task_prompt("AIOS-PROVIDER-SUPERVISED-PILOT")

        self.assertEqual(task["id"], "AIOS-PROVIDER-SUPERVISED-PILOT")
        self.assertEqual(resolved_task["project"], "ai-os")
        self.assertIn("Task contract: .nexora/tasks/AIOS-PROVIDER-SUPERVISED-PILOT.md", prompt)
        self.assertIn("- .nexora/AI-OS-ORCHESTRATION.md", prompt)
        self.assertIn("Stay inside the allowed scope.", prompt)


if __name__ == "__main__":
    unittest.main(verbosity=2)
