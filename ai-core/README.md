# NEXORA QR — Restaurant Menu & Ordering

NEXORA QR is a mobile-first digital menu and ordering system for restaurants and cafés. The application is built with Laravel 12, Inertia, React 19, TypeScript, Tailwind CSS 4, and a relational database.

## Product capabilities

### Restaurant workspace
- Tenant-aware restaurant workspace with owner/manager authorization.
- Create and manage multiple menus, sections, products, images, product variants, and optional/required modifiers.
- Live mobile/tablet/desktop menu preview, reusable theme presets, custom colors, and responsive restaurant templates.
- Publish/unpublish menus and copy the public menu link.
- Generate a high-resolution QR code in the browser and download it as PNG for table tents, stickers, or printed menus.
- Configure pickup, delivery, delivery fee, and restaurant WhatsApp contact.

### Customer ordering
- Public menu that only exposes active published menus and available products.
- Cart with variants, modifiers, quantity limits, pickup/delivery choice, delivery address, customer contact, and order notes.
- Server-side price calculation; client-submitted prices are never trusted.
- Orders and line-item snapshots preserve product names, prices, variants, and modifiers at order time.
- Private customer order-tracking URL using a random token. Only a SHA-256 token hash is stored in the database.
- Customer tracking page refreshes status automatically while open.

### Restaurant operations
- Order dashboard with search, status and fulfillment filters, key operational totals, and CSV export of the currently loaded/filtered orders.
- Polling for new orders, in-page notices, and optional browser notifications (permission must be granted by the user).
- Controlled order state transitions: new → confirmed → preparing → ready → completed; cancellation is allowed before completion.
- Status history records transitions and the authenticated team member who made each change.
- WhatsApp links for contacting the customer or opening the order details for the restaurant.

## Requirements

- PHP `^8.2` and Composer
- Node.js 20+ and npm
- A database supported by Laravel (SQLite for local development; MySQL/MariaDB recommended for production)

## Local development setup

Run these commands from the `ai-core` directory:

```powershell
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
npm install
```

Configure `.env` for the local database. For SQLite, create the database file if it does not exist and set:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Then migrate and build the front end:

```powershell
New-Item -ItemType File -Force database/database.sqlite
php artisan migrate
php artisan db:seed
npm run build
```

For development with hot reload, use two terminals:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Open the URL printed by `php artisan serve`. The first user/restaurant onboarding path depends on the application's existing registration and tenant setup; verify the owner has an active restaurant membership before testing the menu builder.

## Test and quality checks

```powershell
php artisan test --compact
npm run build
git diff --check
npm audit
```

Tests use an in-memory SQLite database. Do not run `migrate:fresh` against a production database.

## Production checklist

1. Set `APP_ENV=production`, `APP_DEBUG=false`, a strong generated `APP_KEY`, and the correct HTTPS `APP_URL`.
2. Configure production MySQL/MariaDB credentials, persistent storage, backups, log rotation, cache/session/queue drivers, and mail if needed.
3. Run migrations in a controlled release window, then `php artisan optimize` and `npm run build` as part of deployment.
4. Ensure the web server points to Laravel's `public` directory, serves HTTPS, and can write to `storage` and `bootstrap/cache`.
5. Test a real phone scanning the QR code, submitting pickup and delivery orders, and opening the private tracking link from outside the server.
6. Verify browser notification permission, WhatsApp phone formatting, uploaded image handling, backups, and tenant isolation with separate restaurant accounts.
7. Do not enable paid integrations, external messaging services, or production deployment without explicit approval and credentials.

## Important implementation notes

- Public menu URLs are `/r/{restaurantSlug}/menu/{menuSlug}`.
- Order tracking URLs are generated after order creation and contain a private, unguessable token. Do not post those links publicly.
- Public order creation is rate-limited and validated server-side; status changes are authenticated, tenant-scoped, authorized, and transactionally validated.
- QR generation is local in the browser; the menu URL is not sent to a third-party QR service.
- Before a real launch, perform an end-to-end acceptance test against the actual production-like database and hosting configuration. Passing automated tests and a front-end build does not replace this final operational check.
