# NEXORA QR — Internal Provider Layer

All agent/provider integration contracts live under `.nexora/providers/`.

The repository owns:
- provider roles
- capability requirements
- routing/fallback policy
- execution contracts
- knowledge-source manifests

The repository does **not** own:
- API keys
- OAuth tokens
- production credentials
- personal access tokens
- billing secrets

Those are injected by the execution environment.

## Provider roles

| Role | Purpose | Required |
|---|---|---|
| coding-primary | Main implementation agent | Yes |
| coding-review | Independent code review | Recommended |
| test-verifier | Test/build/E2E verification | Recommended |
| research | Current libraries/platform/provider research | On demand |
| design | UI/UX and visual-system review | On demand |

A provider can satisfy more than one role.

## Routing

Primary → verification → repair → independent review.

A fallback provider may be selected only when the primary runtime is unavailable or fails its contract.
