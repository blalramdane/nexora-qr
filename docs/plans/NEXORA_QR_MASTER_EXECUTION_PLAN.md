# NEXORA QR — Master Execution Plan

Canonical execution plan from Phase 1 to pilot-ready MVP.

## Order
1. Menu CRUD
2. Category Builder
3. Product Builder
4. Variants
5. Modifiers
6. Image Pipeline
7. Drag & Drop Builder
8. Theme Editor
9. Template Versioning
10. Fast Food production template
11. Café production template
12. Fine Dining production template
13. Publish workflow
14. Public Menu
15. QR system
16. Tables / QR resolver
17. Customer ordering
18. Cart / checkout
19. Order backend
20. Restaurant order dashboard
21. WhatsApp MVP notification
22. Analytics
23. Admin / activity logs
24. E2E testing
25. Pilot restaurants
26. Production hardening

## Lifecycle
Requirement → Evidence → Architecture → Implementation → Test → Build → Verify → State update

On failure:
Observed Behavior → Evidence → Root Cause → Minimal Fix → Test → Verify

## Architecture
Business Data → Menu Domain → Template Engine → Theme → Components → Public Menu → QR / Ordering

Templates never own business logic.

## MVP definition
Restaurant can register, create a menu, choose a template, create categories, add products, variants/modifiers, upload images, customize theme, publish, generate QR, receive customer orders, update order status, and record analytics.

## Scope protection
Do not add AI chatbot, payments, loyalty, delivery marketplace, POS synchronization, advanced CRM, or advanced automation before the core workflow is proven.

## Quality gates
Backend tests, TypeScript, production build, E2E where applicable, security review, performance review, and explicit verification.
