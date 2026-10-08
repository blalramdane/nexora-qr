# NEXORA QR — Agent Provider Contract

The repository runner is provider-neutral.

## Required contract

Set:

`NEXORA_AGENT_CMD`

to an approved coding-agent command available in the execution environment.

The command receives the generated task prompt on stdin.

## Security

- Never commit API keys or provider credentials.
- Prefer environment/secret-manager injection.
- Never grant production credentials to the autonomous coding agent.
- Keep merge and deployment human-controlled.
- Restrict repository permissions to the minimum required.
- Use a disposable or isolated workspace for autonomous runs when possible.

## Provider requirements

The selected provider/runtime must be able to:
1. read the repository
2. edit files
3. run tests/builds
4. inspect failures
5. make bounded repairs
6. update the NEXORA state/run log
7. return a machine-readable or clearly structured execution summary

## Recommended architecture

Repository control plane
→ isolated coding-agent runtime
→ test/build environment
→ Git branch
→ CI verification
→ human review
→ merge

The provider is replaceable; the NEXORA execution protocol is the stable contract.
