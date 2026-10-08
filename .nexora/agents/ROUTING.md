# NEXORA QR — Agent Routing

## Standard implementation

1. Coordinator reads the current step.
2. Builder implements only that step.
3. Verifier runs required gates.
4. Builder repairs failures up to the configured limit.
5. Reviewer checks the resulting diff.
6. Coordinator updates state and run log.

## Visual work

Design Reviewer → Builder → Verifier → Reviewer.

## Research-dependent work

Researcher → Decision/Source record → Builder → Verifier.

## Failure

Evidence → root cause → minimal repair → verification.

If the provider cannot satisfy the role contract, stop rather than silently lowering quality.
