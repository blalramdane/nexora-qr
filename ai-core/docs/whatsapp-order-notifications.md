# Restaurant order notifications

NEXORA keeps every accepted QR order in the restaurant's Orders dashboard. The two restaurant settings are independent:

- `dashboard_order_notifications_enabled` controls the in-dashboard/new-browser notification. The Orders page continues refreshing the order list every 15 seconds regardless of this setting.
- `whatsapp_order_notifications_enabled` queues a WhatsApp Cloud API template message to the restaurant's `whatsapp_phone` after a new QR order has been committed. Provider failures do not roll back or remove the order.

## Configure WhatsApp Cloud API

Set these server-side environment variables; never expose the access token to browser code:

```dotenv
WHATSAPP_CLOUD_API_TOKEN=
WHATSAPP_CLOUD_PHONE_NUMBER_ID=
WHATSAPP_CLOUD_API_VERSION=
WHATSAPP_ORDER_TEMPLATE_NAME=restaurant_new_order
WHATSAPP_ORDER_TEMPLATE_LANGUAGE=ar
```

Use a Meta WhatsApp Business Platform / Cloud API sender with the `whatsapp_business_messaging` permission. Create and get approval for a body template whose four text variables are in this order:

1. Order number
2. Customer name
3. Short order-item summary
4. Total, including the `EGP` currency label

Example Arabic template body:

```text
طلب جديد من NEXORA
رقم الطلب: {{1}}
العميل: {{2}}
الأصناف: {{3}}
الإجمالي: {{4}}
راجع لوحة الطلبات لتأكيد الطلب ومتابعته.
```

After environment changes, reload/restart the Laravel process so the new configuration is read. Then start a supervised queue worker for this app (for example `php artisan queue:work database --queue=default --tries=3 --timeout=15`). In local development, the project-local worker can be started separately from the web server. Production should run it under the host's process supervisor.

The settings screen deliberately blocks enabling automatic WhatsApp notifications until the required server configuration and restaurant recipient number exist. API acceptance is recorded as `sent`; final delivery/read receipts require a separately configured Meta webhook and are not claimed by this integration.

## Customer WhatsApp handoff

After a customer submits the NEXORA QR checkout, the confirmation can also offer **إرسال تفاصيل الطلب على واتساب المطعم**. This creates a prefilled `wa.me` message containing the already-created order number, server-confirmed total, and item summary. The customer must tap Send in WhatsApp. This action is a convenience fallback and does not mean the restaurant has received or accepted the message; the order remains in the NEXORA Orders dashboard regardless.

Avoid manually creating a second order from this message. Use the embedded order number to find the existing record. For automatic notifications without customer interaction, configure the official Cloud API and a supervised queue worker as described above.
