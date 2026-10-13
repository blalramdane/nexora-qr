# NEXORA QR — Product Strategy

**Product promise:** From an existing paper menu to a reliable, order-ready digital menu with less work, fewer mistakes, and measurable results.

## 1. Positioning

NEXORA QR should not compete as "another QR menu". Position it as the easiest way for an Egyptian restaurant to launch, keep accurate, and improve its direct digital ordering experience.

**Primary promise:** `من المنيو الورقي لطلبات منظمة — من غير شغل يدوي مرهق.`

**Three proof points:**

1. **Launch fast:** Import existing menu photos or CSV, review uncertain names/prices, pick a restaurant-fit template, preview, publish, and download a QR.
2. **Keep every order accurate:** One source for price, availability, sizes, and modifiers; customer totals are recalculated server-side; orders retain price/product snapshots and a private tracking link.
3. **Improve with evidence:** Help the restaurant see which menu products are viewed, added, ordered, unavailable, or repeatedly abandoned, then recommend the next practical action.

## 2. Target customer and the real job

### Primary launch segment

Independent restaurants, burger/fried-chicken brands, cafés, and small local chains in Egypt that update prices often, receive orders over the phone/WhatsApp, or have a printed menu that takes time to rework.

### Jobs to be done

- Turn a real menu into a usable digital menu without retyping every row.
- Update a price or mark a product unavailable once and have the customer see the current version.
- Let customers find a product, understand its price/options, and submit an order without calling to clarify basics.
- Give the manager a clear order queue and a safe way to update order statuses.
- Let the owner install the responsive dashboard to the home screen, edit menu photos/prices on a phone, and choose automatic WhatsApp alerts or a customer-initiated WhatsApp handoff when appropriate.
- Know whether QR visits lead to orders instead of assuming that more scans mean more sales.

## 3. Recommended product experience

### A. Restaurant activation

The shortest useful workflow is:

`Create restaurant → Scan menu photos / import CSV → Review names & prices → Choose a template → Preview on mobile → Publish → Download QR → Place a test order.`

The experience must show progress and clearly explain what is missing. Never auto-publish AI-extracted data. Unreadable prices must be corrected by a human before applying the import.

### B. Customer experience

- Arabic-first, mobile-first, fast, and readable on low-end phones.
- Offer a clear post-order handoff to WhatsApp when the restaurant has a valid contact number, while explaining that the order already exists and a WhatsApp message is not proof of restaurant acceptance.
- Search by product or category with a clear empty-results recovery action.
- Show real restaurant/product photos only when supplied; do not mislead diners with unrelated stock photos as though they depict the actual dish.
- Keep the current price, product availability, variant price, modifier cost, delivery fee, and estimated total visible before confirmation.
- Minimize mandatory fields and preserve useful order recovery/tracking after submission.
- A dine-in QR should keep table context and avoid asking for delivery information.

### C. Restaurant operations

- An understandable order queue: new, accepted, preparing, ready, completed, and cancelled where permitted.
- Distinguish a customer order being received from a WhatsApp notification being delivered.
- If browser/WhatsApp notification delivery fails, keep the order persisted and visibly pending in the dashboard.
- Make unavailability and price changes quick to apply; avoid forcing restaurant owners to edit the entire menu to make one small correction.

## 4. Differentiation to build

### Priority 1 — Reliable launch, not an AI demo

Treat menu photo extraction as a review workflow with confidence indicators, null for unreadable prices, and an explicit apply step. Measure how much correction is required and which layouts fail on Arabic numerals, EGP prices, size choices, and handwritten/low-quality images.

### Priority 2 — Trustworthy menu data

Use one authoritative product/price/availability record. Never trust client-submitted totals. Keep price snapshots on orders. Show genuine product imagery or a deliberate branded placeholder instead of a random substitute image.

### Priority 3 — Measure the order funnel

Add privacy-aware events with minimal data collection:

- `menu_view`
- `menu_search`
- `product_view`
- `add_to_cart`
- `checkout_started`
- `order_submitted`
- `order_confirmed`
- `order_completed`

Each event should include the restaurant/menu IDs, timestamp, and a short-lived pseudonymous session identifier; avoid storing sensitive data that is not needed for these metrics. Define an explicit retention period and document analytics in the privacy notice before launching tracking.

### Priority 4 — Actionable owner insights

Do not show charts without decisions. Examples:

- "This item is viewed often but rarely added" → check the product photo, description, price, or options.
- "Many orders are abandoned after delivery selection" → check required address fields, delivery areas, and fee clarity.
- "Top-selling item is frequently unavailable" → check stock/availability workflow.
- "Many products have no actual image" → bulk-upload or request photos.

Keep these recommendations clearly labeled as signals until enough real data exists; never fabricate conversion percentages or claim revenue lift without evidence.

## 5. MVP scope and order of investment

### P0 — Make the current core trustworthy

- Arabic-first navigation and clear no-results/empty states.
- Photo/CSV import entry point visible in the builder.
- Review extracted data before applying it.
- Accurate category filtering, working cart entry points, real-photo-only display.
- Pre-publish checklist and a full customer-order smoke test.
- Reliable mail/notification configuration and visible failure states.
- Tenant-isolation, pricing, modifiers, order status, and tracking tests.

### P1 — Prove business value

- Privacy-aware menu/order funnel events.
- A simple owner performance report: QR visits, product views, cart starts, orders, completion rate, top items, unavailable items.
- Actionable nudges based only on measured signals.
- A low-friction repeat-order experience from the customer's previous tracking/confirmation flow, subject to privacy and retention policy.

### P2 — Expand after evidence

- Smart suggested pairings or add-ons only when grounded in real order data and approved by the restaurant.
- Multiple branches with branch-specific availability/prices where required.
- Integrations with POS/payment/loyalty systems when customers request them and the integration economics are validated.
- More independent template layouts after usability and conversion tests show a clear benefit.

Do not prioritize a chatbot, loyalty scheme, many decorative templates, or broad integrations over reliability of menu data, ordering, and measurement.

## 6. Metrics that define success

Establish a baseline with pilot restaurants first; do not invent starting values. Track:

- **Time to publish:** account creation to first verified published menu.
- **Setup completion:** restaurants that publish after starting onboarding.
- **Import correction rate:** share of extracted products/prices changed by the owner before apply.
- **Order completion rate:** submitted orders that reach completed status.
- **Order error rate:** orders requiring correction due to missing/incorrect product, option, price, address, or availability.
- **Direct-order conversion:** valid menu sessions that result in an order, segmented by dine-in/pickup/delivery.
- **Time to update:** time required to update one price or availability state.
- **Operational reliability:** order creation failures, duplicate submissions, notification failures, and recovery time.
- **Owner retention:** restaurants still editing the menu or receiving orders after 30 days.

Segment results by restaurant type, channel, device, and order type. Compare changes against each restaurant's own baseline; avoid implying causation from simple before/after comparisons.

## 7. Go-to-market proof

Run a controlled pilot with a small set of restaurants, starting with one burger/fried-chicken brand, one café, and one restaurant with a large menu. For each:

1. Time the onboarding from photos to published QR.
2. Have staff and real customers test search, variants, cart, and order tracking on their own phones.
3. Record product corrections and support questions.
4. Compare funnel and order-error measures with the restaurant's baseline.
5. Obtain permission before using customer names, menu data, photos, results, or testimonials in marketing.

Only turn a result into a sales claim after the pilot dataset actually supports it.

## 8. Build and release gates

- A complete happy-path test: create menu, import, review, apply, publish, scan QR from a separate device, submit order, receive it in owner dashboard, change status, and open customer tracking.
- On supported mobile browsers, install the dashboard to the home screen; verify the app icon, launch URL, order dashboard, product-photo edit flow, and WhatsApp handoff. Confirm offline fallback never presents stale private orders as current.
- Negative tests for invalid price, unavailable product, bad modifier selection, duplicate submit, cross-tenant access, and failed notifications.
- Test actual production-like database, file storage, HTTPS URL, mail transport, and OpenAI credentials on staging before release.
- Do not run migrations with production data effects without a verified backup, migration review, and explicit release window.
- Do not publish/deploy or enable paid services until the target environment and required credentials are verified.
