# Nexora QR — READ-ONLY Audit Report

Prepared from live inspection only. No project files were read for secrets; no project files were modified, created, deleted, or renamed.

## Repo paths inspected
- External (root wrapper): D:\project me\New folder\qr nexora
- Internal app repo: D:\project me\New folder\qr nexora\nexora-qr

## A. Git

### External repo (root)
- Branch: feat/menu-visual-overhaul
- HEAD: 4683c55 feat(menu-builder): add product image dropzone and instant preview
- Remote: origin https://github.com/blalramdane/nexora-qr.git (fetch + push)
- git status --short --branch:
  ## feat/menu-visual-overhaul...origin/feat/menu-visual-overhaul
  ?? nexora-qr/
  ?? "t `n=== REMOTE SYNC ==="
  ?? "tatus -sb"
- Last 10 commits (oldest -> newest):
  7e032ab feat(qr-menu): polish FastFoodTemplate interactions
  fae74a1 feat(qr-menu): polish CafeTemplate interactions
  2276750 feat(qr-menu): polish FineDiningTemplate interactions
  33782ed feat(menu): add live builder preview controls
  1489e06 feat(menu): show product drafts in live preview
  c31e5ae feat(menu): support selected preview category
  31ec90f refactor(menu): narrow preview category effect dependencies
  2d4b2ba feat(menu-builder): upgrade product editor with live preview and image dropzone
  cc4701d feat(products): support image upload during product creation
  4683c55 feat(menu-builder): add product image dropzone and instant preview
- Local branches: feat/menu-visual-overhaul only.
- Remote branches: origin/main, origin/feat/menu-visual-overhaul, origin/feat/phase-1-menu-design, origin/feat/phase-0-foundation, origin/feature/phase-0-foundation, origin/docs/design-intelligence-2026.

### Internal repo (nexora-qr)
- Branch: feat/menu-visual-overhaul
- HEAD: 4683c55 (same snapshot as external)
- Remote: origin https://github.com/blalramdane/nexora-qr.git (fetch + push)
- git status --short --branch:
  ## feat/menu-visual-overhaul...origin/feat/menu-visual-overhaul
   M ai-core/resources/js/Pages/Menu/CafeTemplate.tsx
   M ai-core/resources/js/Pages/Menu/FastFoodTemplate.tsx
   M ai-core/resources/js/Pages/Menu/FineDiningTemplate.tsx
- Local branches: feat/menu-visual-overhaul, main (3ee686f Initial POS project).
- Remote branches: same list as external.
- Uncommitted edits: three template files modified in working tree.

### Repo relationship / differences
- External repo does not track nexora-qr/ at all (untracked). The internal repo is the actual app.
- Both repos point to the same remote and same HEAD snapshot, suggesting the external folder is either a wrapper or an outer repo containing a nested internal clone that is not part of the outer git tree.
- Untracked files in external root: nexora-qr/, t `n=== REMOTE SYNC ===, tatus -sb.
- Non-secret config files present (names only): ai-core/.env, ai-core/.env.example.

## B. Architecture

### Versions (from composer.json / package.json / vite.config.ts / tsconfig.json)
- Laravel 12 (^12.0), Inertia Laravel 2 (^2.0), Sanctum 4 (^4.0), PHP 8.2+ (^8.2)
- React 19 (^19.0.0), @inertiajs/react 2 (^2.0.0), Tailwind 4 (^4.0.0), Vite 7 (^7.0.7), TypeScript 5.7 (^5.7.0), laravel-vite-plugin 2 (^2.0.0)
- PHPUnit 11 (^11.5.50)

### Directories (nexora-qr/ai-core)
- app/Http/Controllers, app/Models, app/Http/Middleware, app/Providers, app/Support/Tenancy, app/Models/Concerns, app/Models/Scopes
- database/migrations, database/seeders, database/factories
- resources/js/Pages/Menu, resources/js/Pages/Auth, resources/js/Pages/Dashboard
- routes/web.php, routes/api.php, routes/console.php
- tests/Feature, tests/Unit, tests/TestCase.php

### Routes/web.php
- guest: login, register
- auth + tenant:
  - dashboard
  - menus index/store/show/update/theme-update/publish/unpublish/destroy
  - menu categories store/update/destroy/reorder
  - products store/update/destroy/reorder/image store/destroy
  - product variants store/update/destroy/reorder
  - modifiers store/update/destroy/reorder
- / redirects to /dashboard
- routes/api.php: currently only a placeholder comment; no registered API routes.

### Controllers
- AuthController, DashboardController, MenuController, MenuCategoryController, ProductController, ProductImageController, ProductVariantController, ModifierController, MenuPublishController, PublicMenuController.
- MenuController::show() renders Menu/Builder. MenuController::store() resolves active template version for the chosen template.

### Middleware
- SetTenant: picks the first active restaurant for the authenticated user and sets TenantContext.
- HandleInertiaRequests: shares auth.user and flash.success.

### Models
- Restaurant, Branch, User, Template, TemplateVersion, Menu, MenuCategory, Product, ProductVariant, Modifier.
- Concerns: BelongsToRestaurant trait (adds RestaurantScope + auto restaurant_id from TenantContext on create).
- Scopes: RestaurantScope (closes queries when no tenant; otherwise filters by restaurant_id).
- Tenancy: TenantContext singleton, SetTenant middleware.

### Services / Jobs / Policies / Requests / Resources
- None present as dedicated directories. Authorization is done via private authorizeMenuManagement() methods duplicated across controllers.

## C. NEXORA QR (feature journeys, templates, docs)

### docs/implementation-plan.md
- Decisions log D1-D8 present.
- Phases 0-4 described; SQLite dev / MySQL prod, Inertia+React monolith, wa.me WhatsApp MVP, ai-core as canonical repo.
- Requires TenantIsolationTest before features, server-side PricingService, idempotency, QR short_code/tracking_token, order state machine, activity logs, menu_events, whatsapp_messages_log — none currently implemented.

### Master Execution Plan (docs/plans/NEXORA_QR_MASTER_EXECUTION_PLAN.md)
- Ordered steps 1..26. Current state appears to have completed through about step 13 (Publish Workflow), but step 14 (Public Menu) is missing the page, and steps 15-21 (QR, tables/QR resolver, customer ordering, cart/checkout, order backend, restaurant order dashboard, WhatsApp MVP) are not implemented.

### User journey - implemented
- Register/login: AuthController.
- Create menu with template choice: MenuController store -> Menu/Builder.
- Manage categories: MenuCategoryController.
- Add/edit products with image upload: ProductController + ProductImageController.
- Variants and modifiers: ProductVariantController + ModifierController.
- Theme editor: MenuController updateTheme + Builder theme form.
- Live preview inside Builder: Builder.tsx live preview.
- Production templates: FastFoodTemplate, CafeTemplate, FineDiningTemplate.
- Publish workflow: MenuController publish/unpublish + MenuPublishController (logic overlap exists).

### User journey - NOT implemented
- Public menu page for customers (route exists but renders a missing page).
- QR system (no QrCodeService, no qr_codes migration, no resolver).
- Tables / QR resolver.
- Customer ordering, cart/checkout, order backend, restaurant order dashboard.
- WhatsApp MVP notification (no queued wa.me link generation).

### Template files
- resources/js/Pages/Menu/Index.tsx (menu listing and template selection)
- resources/js/Pages/Menu/Builder.tsx (live builder, drag/drop, theme editor, product editor)
- resources/js/Pages/Menu/TemplateRenderer.tsx (renders correct template by key + version)
- resources/js/Pages/Menu/CafeTemplate.tsx
- resources/js/Pages/Menu/FastFoodTemplate.tsx
- resources/js/Pages/Menu/FineDiningTemplate.tsx
- resources/js/Pages/Auth/Login.tsx, Register.tsx

### Missing public-facing page
- PublicMenuController::show() renders Inertia 'Menu/Public', but resources/js/Pages/Menu/Public.tsx does not exist. Result: visiting the public URL would fail to render the public menu.

## D. Quality

### Backend tests (tests/Feature)
- TenantIsolationTest (4 tests): tenant isolation, no-tenant closed queries, auto tenant on create, owner registration creates tenant + main branch.
- MenuPublishWorkflowTest (3 tests): publish/unpublish, publish requires active category + available product, cannot cross tenant boundary (404).
- ProductImagePipelineTest (3 tests): upload/replace/delete, rejects non-images/oversized, tenant boundary.
- TemplateVersioningTest (4 tests): sequential versions, activate, schema history preserved, menu pinned to version, template change pins to new active version.
- CafeTemplateContractTest (1 test): v2 schema contract.
- FastFoodTemplateContractTest, FineDiningTemplateContractTest: present but not read in detail.
- MenuCategoryBuilderTest, ProductBuilderTest, ProductVariantBuilderTest, ModifierBuilderTest, MenuFoundationTest, MenuThemeEditorTest: present.
- ExampleTest (feature): / redirects to /dashboard.

### Unit tests
- tests/Unit/ExampleTest.php only.

### Build/test commands (composer.json / package.json)
- composer test: php artisan config:clear && php artisan test
- composer setup: composer install, copy .env.example to .env if missing, artisan key:generate, artisan migrate --force, npm install, npm run build
- composer dev: concurrently php artisan serve + queue:listen + pail + npm run dev
- npm build: vite build; npm dev: vite
- phpunit.result.cache exists; I did not run tests live and cannot assert current pass/fail results from cache alone.

### Confirmed issues / risks
- PublicMenuController points to a missing Menu/Public page -> public menu route is broken.
- FineDiningTemplate.tsx appears mis-encoded: starts with BOM, money() shows garbled Arabic, and some labels/aria-labels appear corrupted. This is likely a file encoding/UTF-8 issue.
- SetTenant picks the first active restaurant arbitrarily (no tie-break or user choice), which is risky for users with multiple restaurants.
- authorizeMenuManagement() is duplicated as a private method in multiple controllers instead of being centralized in a trait/policy.
- Publish validation logic exists in both MenuController and MenuPublishController; risk of divergence.
- TemplateRenderer reads (menu as any).templateVersion?.version; unsafe and brittle.
- api.php is empty; no webhook HMAC, no QrCodeService, no orders/customers/state machine.
- Image pipeline currently writes directly to public disk without visible magic-byte/encode steps in the reviewed controllers (may be acceptable for MVP but not aligned with the plan's image-pipeline plank).

## E. Execution Plan

### P0 (breaks current functionality / correctness)
1. Missing public menu page. File: routes/web.php + PublicMenuController reference Menu/Public; missing resources/js/Pages/Menu/Public.tsx. Fix: add Menu/Public page that renders the published menu via TemplateRenderer. Verify: GET /{restaurantSlug}/{menuSlug} renders a public menu without auth.
2. FineDiningTemplate.tsx encoding corruption. File: resources/js/Pages/Menu/FineDiningTemplate.tsx. Fix: re-save as UTF-8 without BOM and correct garbled strings. Verify: Arabic labels render correctly in fine-dining preview.
3. SetTenant arbitrary restaurant selection. File: app/Http/Middleware/SetTenant.php. Fix: define selection rule or pending UI for multi-restaurant users; add tests for multi-tenant users. Verify: a user with multiple restaurants is not blindly bound to the first.

### P1 (quality / security / maintainability)
4. Duplicated authorizeMenuManagement() in controllers. Fix: extract to trait or policy. Verify: same authorization outcome everywhere.
5. Publish logic duplicated in MenuController and MenuPublishController. Fix: unify validation in one place. Verify: identical publish rules on all paths.
6. Unsafe template version access in TemplateRenderer. File: resources/js/Pages/Menu/TemplateRenderer.tsx. Fix: use a stable Menu/templateVersion relationship or typed accessor. Verify: no crash on missing version.

### P2 (future / not yet in scope)
7. QR system not implemented (plan step 15). No QrCodeService, no qr_codes migration, no resolver.
8. Customer ordering not implemented (plan steps 17-19). No customers/orders/cart/checkout.
9. WhatsApp MVP notification not implemented (plan step 21). No queued wa.me link generation.
10. Unit test coverage thin; no PricingService/state-machine tests because those layers do not exist yet.

### Small recommended sprint (without rewriting the project)
- Add Menu/Public.tsx for the public menu (renders via TemplateRenderer; add QR placeholder link if real QR generation is not ready).
- Fix FineDiningTemplate.tsx encoding and review Arabic strings across all three templates.
- Improve SetTenant selection or document the limitation clearly until multi-restaurant UX is added.
- Centralize menu authorization and publish validation to reduce drift.

## Notes and open questions
- I did not read ai-core/.env; only its existence is noted.
- I did not modify, create, delete, or rename any project file.
- I did not run tests, migrations, composer install, npm install, or builds.
- Open questions for the team:
  - Is the external root repo intended as a wrapper for nexora-qr/, or should both become one monorepo?
  - What is the intended public URL pattern for restaurant slug + menu slug?
  - What is the correct restaurant-selection rule for users with multiple restaurants?
