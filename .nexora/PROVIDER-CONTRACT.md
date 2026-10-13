# NEXORA QR — Agent Provider Contract

The NEXORA QR repository owns the provider contracts and routing policy under `.nexora/providers/` and `.nexora/agents/`.

## Runtime variables

- `NEXORA_AGENT_CMD` — primary builder
- `NEXORA_REVIEW_AGENT_CMD` — independent reviewer
- `NEXORA_VERIFY_CMD` — verification runtime
- `NEXORA_RESEARCH_CMD` — optional research runtime
- `NEXORA_DESIGN_CMD` — optional visual/design reviewer
- `NEXORA_PROVIDER_TIMEOUT` — optional adapter timeout in seconds (default 1800; maximum 3600)

Commands are injected by the execution environment. **Never commit credentials or provider-specific secrets.**

The supervised adapter lives in `.nexora/provider-adapter/aios_provider.py`. It reports configured role readiness without exposing command values, runs one bounded task through the configured CLI, and writes a structured run report. A provider's zero exit code means only that the invocation returned; it does not mark the task complete.

## Capability verification before dispatch

The presence of an environment variable or a provider name is not proof that it works. Before using a provider, verify the command is available and that it satisfies the assigned role's required capabilities. Do not print command values or secrets in logs. If a provider is unavailable, report the role as blocked and continue only with independent tasks that have a verified executor.

Every execution must return a structured report compatible with `.nexora/AI-OS-EXECUTION-REPORT.schema.json`. Required check statuses distinguish passed, failed, skipped, and not_run.

## Required implementation capabilities

A provider used for implementation must be able to:
1. read the repository
2. edit files
3. run tests/builds
4. inspect failures
5. make bounded repairs
6. update NEXORA state/run logs
7. return a structured execution result

## Internal architecture

Repository control plane
→ provider registry
→ agent role routing
→ isolated runtime
→ tests/build/E2E
→ CI
→ human review
→ merge

Providers are replaceable. The NEXORA contracts remain stable.

## Knowledge boundary

Project-safe knowledge lives in the repository. Private NEXORA brains may be mounted/injected at runtime but must not be committed. Only knowledge relevant to NEXORA QR may affect implementation.

## Isolation and security

- Use a disposable/isolated workspace whenever possible.
- Never grant production credentials to autonomous agents.
- Never expose secrets in logs.
- Keep repository permissions minimal.
- Do not allow agents to merge or deploy production automatically.

## Human gates

The autonomous system may prepare code and PRs. These remain human-controlled:
- merge
- production deployment
- product/scope changes
- credential changes
- cross-project knowledge sharing
- installation of new runtime dependencies, tools with broad permissions, or paid services when not already authorized
