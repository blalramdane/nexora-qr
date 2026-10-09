# QR Cloud API — Initial Integration Contract

Status: feature branch implementation draft. Do not configure production or connect a live cashier until the tests pass and the branch-to-POS adapter is implemented.

## Scope implemented in this branch

- Branch identities with unique slug/code and active state.
- Branch-bound POS agent credentials; only SHA-256 token hashes are stored.
- Public read-only menu snapshot endpoint.
- Public QR order submission with server-calculated subtotal, menu-version check, product availability check, and idempotency by branch + source UUID.
- Agent menu publish endpoint, pending-order lease/poll endpoint, and import/reject acknowledgement.
- Artisan commands to provision branch identity and issue a one-time token.

## API endpoints

All endpoints use the Laravel API prefix /api.

### Public

- GET /api/qr/v1/menus/{slug}
- POST /api/qr/v1/menus/{slug}/orders

Example request:

~~~json
{
  "source_order_uuid": "f9f70f64-0fc9-4e61-89ab-8b4d9a4a10b1",
  "menu_version": 1,
  "fulfillment_type": "takeaway",
  "customer_name": "Customer",
  "customer_phone": "01000000000",
  "notes": "",
  "items": [
    { "source_product_id": "pos-product-123", "quantity": 2 }
  ]
}
~~~

The submitted payload never determines price. The API calculates from the published catalog snapshot. A stale menu version returns HTTP 409. Reusing a source UUID with a different normalized order payload returns HTTP 409. A successful new order is stored as pending_delivery and is not paid or yet a native POS order.

### Branch agent

Send the one-time token as Authorization: Bearer <token>.

- PUT /api/qr/v1/agent/menu
- GET /api/qr/v1/agent/orders/pending?limit=20&lease_seconds=60
- POST /api/qr/v1/agent/orders/{id}/acknowledge (durable local inbox receipt)
- POST /api/qr/v1/agent/orders/{id}/resolve (final cashier accept/reject result)

Menu publish request:

~~~json
{
  "menu_version": 1,
  "items": [
    {
      "source_product_id": "pos-product-123",
      "name": "Meal",
      "category_name": "Meals",
      "price_minor": 12500,
      "currency": "EGP",
      "is_available": true,
      "options": []
    }
  ]
}
~~~

price_minor is an integer minor-unit amount (for example, 12500 means 125.00 EGP). The adapter from the existing POS must convert its authoritative catalog price explicitly; never trust a browser-provided price.

Acknowledge example:

~~~json
{
  "delivery_lease_token": "lease-uuid-from-pending-response",
  "status": "imported",
  "local_order_id": "local-order-reference"
}
~~~

Use status=rejected plus a reason when the cashier cannot accept the order. The acknowledge request uses status=received and requires local_inbox_id plus the current delivery lease. This means the order is safely persisted in the local cashier inbox, not yet accepted as a native POS order. After cashier action, resolve with status=imported plus the native local_order_id, or status=rejected plus a reason. Final resolution is retry-safe.

## Provisioning (after deploying to a non-production environment)

~~~sh
php artisan migrate --force
php artisan qr:branch:create "Al Jazeera Branch 1" "aljazeera-branch-1" "AJ01"
php artisan qr:agent:issue aljazeera-branch-1 --name="Branch 1 Primary POS"
~~~

The token is printed once. Store it only in the branch-agent secret store/environment; never place it in the QR browser or source control. Revoke a compromised credential by setting its revoked_at timestamp in the database; a dedicated rotation/revocation command should be added before production.

## Important limitations / next work

- No live production deployment or migration has been performed.
- No credentials or customer data were sent to any cloud service.
- The branch agent client/worker is being implemented on the separate Al Jazeera POS feature branch; it is not yet deployed in the installed cashier build.
- Public QR order submission currently accepts takeaway only. Dine-in/table and delivery/address flows stay disabled until their POS mappings and tests are complete.
- The owner dashboard authentication/reporting UI is not implemented yet.
- The menu options field is currently a snapshot payload; strict option/modifier validation against an allow-list must be added before enabling options in production.
- The QR inbox-to-native-POS conversion must call existing POS domain services and be tested against open-shift, payment, kitchen, inventory, and print workflows.
- Branch creation/token issuance are CLI-only; secure owner administration UI/API is future work.
- Run composer test from ai-core and production build checks before merging. Test execution has not been performed by this remote source edit.
