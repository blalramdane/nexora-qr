# NEXORA QR — Owner onboarding and menu-photo scan

## 1. Server-only environment

Set these values in the target server's secret/environment settings; do not commit credentials and do not expose the OpenAI key through any `VITE_*` variable.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-PRODUCTION-DOMAIN

MAIL_MAILER=smtp
MAIL_HOST=YOUR-SMTP-HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR-SMTP-USERNAME
MAIL_PASSWORD=YOUR-SMTP-PASSWORD
# For STARTTLS on port 587; use smtps for implicit TLS on port 465.
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=YOUR-VERIFIED-SENDER
MAIL_FROM_NAME="NEXORA QR"

OPENAI_API_KEY=YOUR-OPENAI-API-KEY
OPENAI_MENU_SCAN_MODEL=gpt-5.5
OPENAI_BASE_URL=https://api.openai.com/v1
```

Use the exact SMTP scheme, port, and sender details provided by the mail service. The `log` and `array` mail drivers do not deliver real email; the Owner Studio now warns instead of claiming an invitation was delivered when either is active.

## 2. Safe release sequence

1. Back up the target database and storage, and review every pending migration against that backup. In particular, inspect data-changing migrations before running any production migration.
2. Configure `APP_URL`, production/debug flags, durable database/session storage, the mail transport, and the server-side OpenAI key in the target environment.
3. On staging, run `php artisan test --compact`, `npm run build`, `php artisan route:list --name=password`, and `php artisan route:list --name=owner.menus.scan`.
4. Smoke-test `/up`, `/login`, `/forgot-password`, and a real reset-link flow using the configured mail service.
5. With the OpenAI key configured, upload menu photos, verify that extraction opens the editable review screen, correct uncertain names/prices, and only then submit **Apply import**. The scan step creates a review draft; product/category writes occur only after explicit apply.
6. Verify a restaurant owner can log in, only sees restaurants linked to their account, edits product/price data, previews the public menu, and publishes only after review.

## 3. Operational notes

- Photo scanning accepts up to eight images per request. Unreadable prices should be reviewed manually; do not treat extracted text as authoritative without human review.
- Existing user accounts are linked to the restaurant without changing their password. Newly created owner accounts receive a one-time password-reset link only when real mail delivery is configured.
- Never run migrations against production merely because the application tests pass. Review migration effects, back up the database, and validate the release on staging first.
