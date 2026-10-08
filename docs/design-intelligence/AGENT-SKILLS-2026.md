# NEXORA QR — Agent Design Skills 2026

## Purpose

These are procedural skills for the coding agent, not runtime dependencies. They should be loaded according to the task instead of all being active all the time.

## Recommended skill stack

### 1. frontend-design
Source: `anthropics/skills`

Use when:
- starting a new page/template
- choosing visual direction
- avoiding generic AI-generated UI
- translating product context into visual decisions

### 2. web-design-guidelines
Source: `vercel-labs/agent-skills`

Use when:
- reviewing interface correctness
- checking spacing, typography, interaction and accessibility
- performing a production UI audit

### 3. vercel-react-best-practices
Source: `vercel-labs/agent-skills`

Use when:
- optimizing React performance
- reviewing rendering and data-fetching behavior
- checking bundle and re-render risks

### 4. vercel-composition-patterns
Source: `vercel-labs/agent-skills`

Use when:
- designing reusable React component APIs
- preventing boolean-prop sprawl
- building compound components

### 5. shadcn
Source: official shadcn skill

Use when:
- adding or extending shadcn components
- working with registries
- customizing tokens and variants

### 6. ui-ux-pro-max
Source: `nextlevelbuilder/ui-ux-pro-max-skill`

Use conditionally when a page needs deeper pattern research or a more opinionated UX treatment.

### 7. critique
Source: `pbakaus/impeccable`

Use before redesigning an existing screen to identify concrete visual/UX issues.

### 8. polish
Source: `pbakaus/impeccable`

Use as a final visual refinement pass after functionality is stable.

### 9. delight
Source: `pbakaus/impeccable`

Use selectively for micro-interactions after usability is already correct.

### 10. extract-design-system
Source: `arvindrk/extract-design-system`

Use when auditing an existing NEXORA screen/codebase and extracting tokens/components/patterns into the design system.

### 11. emil-design-eng
Source: `emilkowalski/skill`

Use for motion/detail/craft decisions where animation is part of the product experience.

### 12. React Bits Agent Kit
Source: `reactbits.dev`

React Bits currently exposes design skills, prompts and recipes. Use the specific recipe/skill that matches the page rather than treating React Bits as a universal design system.

## Recommended workflow

`Context → UX Pattern → Visual Direction → Design System → Implementation → React Review → UX Review → Polish`

Example for a new Cafe template:

1. frontend-design
2. UX Patterns for Developers: product-card/search/cart patterns
3. NEXORA template/theme tokens
4. shadcn/Base UI/React Aria primitives
5. Motion Primitives or React Bits only where needed
6. web-design-guidelines
7. critique
8. polish

## Important rule

Skills are instructions for the agent. They are not a reason to add more packages to NEXORA.

Keep the runtime dependency graph intentionally small.

## Current external skill ecosystem reference

https://www.skills.sh/topic/design
https://www.skills.sh/topic/react
https://www.skills.sh/
