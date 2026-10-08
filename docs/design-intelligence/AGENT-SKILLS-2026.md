# NEXORA QR — Agent Skills 2026

## Product UX

- Design for thumb-first mobile interaction.
- Treat Arabic RTL as a primary layout, not a translation layer.
- Keep the next action obvious on every screen.
- Prefer progressive disclosure over crowded forms.
- Use stable components and tokens rather than one-off styling.
- Always implement loading, empty, error, disabled, unavailable, and success states.

## Menu builder

- Optimize for restaurant staff who may not be technical.
- Minimize typing and repeated data entry.
- Keep preview and editing mentally connected.
- Preserve ordering and sort state explicitly.
- Never let visual templates own pricing, availability, or ordering rules.

## Public menu

- Prioritize fast first render and readable typography.
- Make category navigation easy to scan.
- Keep product pricing and availability unambiguous.
- Avoid heavy visual effects that harm mobile performance.
- Keep ordering actions reachable without destroying menu context.

## Engineering/design boundary

Business rules belong in domain/backend layers.
Presentation rules belong in templates/components.
Theme data should be serializable and validated.
Template versions must be stable enough to render existing menus after future template changes.

## Quality

Before a step is marked complete:
- backend tests pass
- TypeScript passes
- production build passes
- relevant E2E/visual checks pass
- tenant/security boundaries are reviewed
- mobile and RTL behavior is verified where applicable

## Self-improvement

Agents may improve abstractions when evidence shows duplication, fragility, accessibility problems, or performance regressions.

Agents must not change product scope, architecture, or execution gates without recording a decision.
