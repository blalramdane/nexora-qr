# NEXORA QR — Knowledge Manifest

This file defines how project knowledge is consumed without leaking private material into the public repository.

## Repository-safe knowledge

- `docs/plans/`
- `docs/design-intelligence/`
- `.nexora/`
- source code
- tests
- architecture documentation

## Private knowledge sources

The following may be mounted/injected into the agent runtime but must not be committed:

- NEXORA Company AI Brain
- NEXORA Company Creation & Client Acquisition Brain
- NEXORA Creative Growth & Facebook Client Acquisition Brain

These sources are strategic/internal knowledge. Agents may use them for relevant decisions, but secrets, customer PII, credentials, and unrelated project information must remain isolated.

## Project boundary

Only knowledge relevant to **NEXORA QR** may influence implementation. Do not import decisions, credentials, pricing, architecture, or brand assets from another NEXORA project unless explicitly approved.

## Priority

Current explicit user decision
→ current project files
→ current project decisions
→ approved private NEXORA knowledge
→ external research
→ agent inference
