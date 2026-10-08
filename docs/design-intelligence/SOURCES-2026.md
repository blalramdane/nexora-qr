# NEXORA QR — 2026 Design & UX Source Registry

This is the source registry behind the primary Design Intelligence reference.

## UI foundations

### shadcn/ui
https://ui.shadcn.com/

Use for composable application primitives and a copy/adapt workflow.

### Base UI
https://base-ui.com/

Use for headless, accessible and composable primitives when NEXORA needs full visual control.

### React Aria Components
https://react-aria.adobe.com/

Use as the accessibility, internationalization and complex-interaction reference.

## Application UI

### Origin UI
https://originui.com/

Polished React/Tailwind application components. Useful for forms, settings, navigation, tables, dialogs and interaction variants.

### Tremor
https://www.tremor.so/

React/Tailwind dashboard and chart components. Use for merchant analytics, KPIs and reporting.

## Visual differentiation

### React Bits
https://reactbits.dev/

Components, blocks, app UI, templates and agent skills. Strong reference for text effects, micro-interactions and signature visual moments.

### Motion Primitives
https://motion-primitives.com/

Reusable animated components built around Motion and Tailwind. Use for controlled transitions and state feedback.

### Magic UI
https://magicui.design/

Animated React/TypeScript/Tailwind/Motion components. Use selectively for premium visual treatments.

### Aceternity UI
https://ui.aceternity.com/

React/Tailwind/Motion components, 3D effects, cards, backgrounds, hero blocks and templates. Use selectively and avoid heavy effects in the ordering path.

### Lucide
https://lucide.dev/

Default NEXORA icon system. Tree-shakable React icons with consistent stroke language.

## UX research and skills

### UX Patterns for Developers
https://uxpatterns.dev/

Implementation-oriented patterns and decision guides for product cards, data display, search, overlays, loading, pagination and accessibility.

Useful skill example:

`npx skills add https://github.com/thedaviddias/ux-patterns-for-developers --skill product-card`

### Baymard Institute
https://baymard.com/

Use as the research/benchmark source for mobile ecommerce UX, product listing, filtering, checkout, search and navigation.

## 2026 evidence

- shadcn/ui documents multiple component bases; its July 2026 update made React Aria a first-class base alongside Base UI and Radix.
- React Aria documents 50+ components with built-in behavior, adaptive interactions and internationalization.
- Base UI is unstyled and designed for accessible, composable React interfaces.
- Origin UI provides a large React/Tailwind copy-and-paste component collection.
- Tremor provides open-source React/Tailwind dashboard and chart components.
- React Bits exposes components, blocks, app UI, templates and agent skills, including a broad micro-interaction catalog.
- Motion Primitives provides reusable animated components around Motion + Tailwind.
- Magic UI provides 150+ free/open-source animated components and effects.
- Aceternity provides a large React/Tailwind/Motion catalog with components, blocks and templates.
- Baymard's 2026 mobile UX benchmark covers thousands of manually reviewed UX elements and shows significant room for improvement.
- UX Patterns for Developers provides implementation-level patterns plus installable skills.

## Rule

These are reference sources, not a reason to install every package. Keep the NEXORA runtime dependency graph small and copy/adapt patterns when practical.
