# NEXORA QR — Design Intelligence 2026

Status: **Primary design reference**
Updated: 2026-10-08

## Purpose

This document defines the design-engineering reference layer for NEXORA QR.

NEXORA QR must not look like a generic restaurant QR menu, a copied shadcn dashboard, or a collection of unrelated UI effects.

The goal is premium visual quality, mobile-first UX, strong merchant usability, Arabic RTL first-class support, accessible interactions, reusable templates, activity-specific visual identity, controlled motion, and production performance.

## Design doctrine

**Foundation → Patterns → Components → Templates → Activity Theme → Motion**

Do not start with visual effects.

1. Foundation: typography, spacing, tokens, accessibility, RTL, responsive behavior.
2. Patterns: navigation, search, filters, product cards, cart, checkout, dialogs, tables, dashboard states.
3. Components: reusable UI primitives and compound components.
4. Templates: complete menu compositions.
5. Activity theme: restaurant/cafe/bakery/etc. visual personality.
6. Motion: purposeful micro-interactions, never decoration that blocks the task.

## Recommended 2026 reference stack

### Core UI foundation
- **shadcn/ui** — composable copy/paste application primitives.
- **Base UI** — headless accessible primitives when we need more control.
- **React Aria Components** — accessibility, internationalization and adaptive interaction reference.
- **Tailwind CSS** — styling/token implementation.

### Visual component sources
- **Origin UI** — polished application components and interaction variants.
- **React Bits** — distinctive visual components, text effects, micro-interactions and agent skills.
- **Motion Primitives** — reusable animated components based on Motion + Tailwind.
- **Magic UI** — animated components/effects for selective high-impact surfaces.
- **Aceternity UI** — distinctive animated/3D components and page blocks; use selectively.
- **Lucide** — consistent, tree-shakable icon system.

### Data/dashboard
- **Tremor** — charts, KPI cards, data display and dashboard patterns.

### UX pattern/skill reference
- **UX Patterns for Developers** — product-card, data-display, search, overlay, loading, pagination and decision-pattern references.
- **Baymard Institute** — mobile/ecommerce UX research and benchmark reference.

## What we should actually use in code

### Primary
1. shadcn/ui or Base UI for primitives.
2. React Aria where accessibility/complex interaction is important.
3. Lucide for icons.
4. Tailwind tokens for the NEXORA design system.
5. Motion for controlled transitions.

### Secondary
- Origin UI for component variants and visual references.
- Tremor for merchant analytics.
- React Bits / Motion Primitives for selected signature interactions.
- Magic UI / Aceternity only when the visual effect supports the product goal.

### Never
- install five UI libraries and mix their visual languages on the same screen.
- copy entire templates without adapting them to NEXORA.
- add animation to every element.
- use a 3D effect where it slows ordering or hides product information.
- let a component library define NEXORA's brand identity.

## 2026 design principles

### 1. Mobile-first is mandatory
The customer menu is primarily a phone experience.

Use thumb-friendly actions, sticky but unobtrusive cart, fast category navigation, large food/product imagery, short decision paths, clear price and availability, no horizontal scroll, responsive typography, and progressive disclosure.

### 2. Calm merchant dashboards
The merchant dashboard should behave like an operating surface, not a marketing page.

Use one primary decision per area, role-aware information, clear next action, meaningful empty/loading/error states, drill-down instead of chart walls, restrained color, and compact but readable density.

### 3. Visual differentiation belongs to templates
The underlying components should remain stable while templates can change typography, radius, spacing density, image treatment, category navigation, card composition, color tokens, header/hero style, promotion blocks, and cart presentation.

### 4. Motion must explain state
Good motion: cart feedback, category transitions, image loading, drawers, order status changes, save feedback, subtle hover/tap feedback.

Bad motion: constant floating objects, slow page transitions, decorative effects over product information, or animations that hurt reduced-motion users.

## Activity template direction

| Activity | Visual direction | Signature UI |
|---|---|---|
| Fast Food | bold, energetic, high contrast | quick-add product cards |
| Burger | oversized food photography | stacked burger cards + modifiers |
| Pizza | warm, appetizing, editorial | size/crust/modifier selector |
| Cafe | calm, premium, warm | category chips + compact cards |
| Bakery | editorial, soft, image-led | story/product cards |
| Desserts | playful, colorful | visual grids + featured items |
| Juice/Smoothie | fresh, bright | ingredient/size selectors |
| Fine Dining | restrained luxury | editorial menu sections |
| Food Truck | compact, energetic | fast order flow |
| Beach/Resort | airy, immersive | image-led categories |

## Template architecture

Templates must be data-driven.

A template consumes restaurant branding, theme tokens, menu structure, categories, products, variants, modifiers, availability, promotions, ordering mode, and restaurant/table context.

**Template + Theme + Menu Data + Context → Rendered Menu**

## Component states are mandatory

Every important interactive component should account for default, hover, focus-visible, pressed, disabled, loading, success, error, empty, out-of-stock, long content, narrow mobile viewport, and reduced motion.

## Arabic / RTL

Arabic is not a later translation layer. The design system must support RTL layout, Arabic typography, logical CSS properties, correct icon direction, mixed Arabic/Latin content, long names, and semantic RTL motion.

## Performance guardrails

Prefer CSS transforms, lightweight Motion transitions, lazy-loaded images, responsive image sizes, WebP/AVIF where supported, limited large background effects, code splitting for optional visual components, and no unnecessary animation libraries on every route.

## Accessibility guardrails

Verify keyboard navigation, focus visibility, semantic labels, touch target size, contrast, reduced motion, screen reader announcements for cart/order state, accessible dialogs/drawers, and no color-only status communication.

## Selection rule

When adding a new component:

1. Search this reference first.
2. Prefer existing NEXORA components.
3. If missing, choose one source library.
4. Adapt it to NEXORA tokens.
5. Add tests for behavior and responsive states.
6. Do not introduce a new dependency unless the component cannot reasonably be built from existing primitives.

## Primary conclusion

NEXORA QR should use **one coherent NEXORA Design System** built from proven accessible primitives, while borrowing the best visual patterns from the 2026 ecosystem.

The competitive advantage is not "we use library X".

It is:

**NEXORA Design System + Activity Templates + Excellent Mobile UX + Controlled Motion + Arabic RTL + Fast Ordering.**
