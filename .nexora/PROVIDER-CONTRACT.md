# NEXORA QR — Agent Provider Contract

The NEXORA QR repository owns the provider contracts and routing policy under `.nexora/providers/` and `.nexora/agents/`.

## Runtime variables

- `NEXORA_AGENT_CMD` — primary builder
- `NEXORA_REVIEW_AGENT_CMD` — independent reviewer
- `NEXORA_VERIFY_CMD` — verification runtime
- `NEXORA_RESEARCH_CMD` — optional research runtime
- `NEXORA_DESIGN_CMD` — optional visual/design reviewer

Commands are injected by the execution environment. **Never commit credentials or provider-specific secrets.**

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
