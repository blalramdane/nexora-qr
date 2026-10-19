# NEXORA QR — Release Readiness

**Review date:** 2026-10-19
**Branch:** `feat/menu-visual-overhaul`
**Repository:** `blalramdane/nexora-qr`
**Status:** Local release candidate built; production release is still blocked on release-scope review and production environment verification.

## Changes made during release preparation

- Added root and application `.gitignore` rules for local AI-agent caches, credential/session artifacts, ACL recovery reports, and local visual-QA images. Files were not deleted.
- Excluded the local-only `dsh-our-free-model/` workspace from Docker and Git contexts; it is not a runtime dependency of NEXORA QR.
- Hardened `App\Support\Deployment\SchemaSync`: an "already exists" exception is accepted only when the expected columns, indexes, and foreign keys are present. Partial schema changes now fail visibly instead of silently marking a migration complete.
- Made the `order_reviews` upgrade path compatible with both the legacy schema and the newer schema by adding missing columns and indexes individually.
- Added regression coverage in `tests/Unit/SchemaSyncTest.php` for incomplete columns, incomplete indexes, and genuinely converged schemas.
- Replaced overlong MySQL index/foreign-key names in delivery settlement migrations and added repair for missing keys after partial runs.
- Reworked notification index/foreign-key creation to avoid invalid constraint names and recover an incomplete notification table.
- Corrected loyalty customer links to reference the existing `users` table rather than a nonexistent `customers` table; added missing-key repair and a relationship regression test.
- Updated the Al Saqa menu seeder to reuse the existing `al-saqa-al-zarqa` tenant before creating a tenant, avoiding a duplicate live restaurant; added an idempotency test for repeated imports.
- Aligned GitHub Actions' MySQL service with the deployed MySQL 8.4 baseline.

## Verification results

| Check | Result |
|---|---|
| `php artisan test` | PASS — 175 tests, 1,813 assertions |
| `npm run typecheck` | PASS |
| `npm run build` | PASS — 881 modules transformed |
| `npm audit --omit=dev` | PASS — zero reported vulnerabilities |
| `composer audit` | PASS — no vulnerability advisories |
| Laravel Pint on release-hardening PHP files | PASS |
| `git diff --check` | PASS |
| `docker build -t nexora-qr:release-candidate .` | PASS |
| Release-candidate container smoke test | PASS — `/login` HTTP 200; CSRF and Inertia markup present |
| Fresh isolated MySQL 8.4 migration run | PASS — all migrations applied |
| Repeated migration run on MySQL 8.4 | PASS — `Nothing to migrate` |
| Recovery after isolated partial migration failures | PASS — delivery, settlement, notification, and loyalty migrations converged without dropping test data |

The smoke test and MySQL 8.4 migrations were local and isolated only. They did not connect to production, publish an image, or apply migrations to a production database. The temporary MySQL container and temporary HTTP smoke-test container were removed after verification.

## Release blockers

1. **Review Git scope before committing.** This working tree has many pre-existing modified and untracked files spanning application code, migrations, UI, docs, and AIOS tooling. Do not stage everything as one release. The current Docker image was built from the local working tree, not from a clean, reviewed commit.
2. **Run repository CI from a clean reviewed commit.** Local tests and isolated MySQL 8.4 migrations pass, but CI has not run on a clean pushed commit containing only the approved release scope.
3. **Configure the real target.** Confirm the production domain, `APP_URL`, generated `APP_KEY`, DB credentials, `APP_DEBUG=false`, HTTPS, and secret storage in the hosting provider. Do not commit production env files.
4. **Protect production data.** Inspect the production migration plan and take a restorable database and uploaded-media backup before any migration. Do not use `migrate:fresh`, `db:wipe`, or seeders on production.
5. **Run a production-like acceptance pass.** From a real phone, scan the published QR, submit a pickup and delivery test order, verify it reaches the correct restaurant workspace, track it externally, verify persisted images and queue processing, and confirm tenant isolation.
6. **Verify integrations explicitly.** Configure and test email/WhatsApp only after approved credentials and templates are in place. Keep optional paid integrations disabled until their costs and credentials are approved.

## Commands for the release gate

Run from `ai-core/`:

```powershell
php artisan test
npm run typecheck
npm run build
npm audit --omit=dev
composer audit
vendor/bin/pint --test app/Support/Deployment/SchemaSync.php database/migrations/2026_10_16_000001_create_order_reviews_table.php tests/Unit/SchemaSyncTest.php
vendor/bin/pint --test database/migrations/2026_10_10_150000_create_delivery_network.php database/migrations/2026_10_10_200000_create_delivery_cash_settlements.php database/migrations/2026_10_10_210000_create_delivery_rider_ledger_entries.php database/migrations/2026_10_16_000002_create_notifications_table.php database/migrations/2026_10_17_041538_create_loyalty_points.php app/Models/LoyaltyPoint.php tests/Feature/LoyaltyPointsTest.php
git diff --check
docker build -t nexora-qr:release-candidate .
```

## Publish/deploy status

- No Git commit or push was performed.
- No registry image was published.
- No production service was deployed or restarted.
- No production migration was run.

The next release step is to classify and review the existing working-tree changes, prepare a clean, reviewed commit, and run CI. Production deployment remains a separate approved operation.
