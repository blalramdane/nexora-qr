# NEXORA — Demo & Client Outreach Operating System

Status: implementation specification, working Local Scout integration, and launch checklist. No client messages have been sent and no public deployment has been performed.

## Implemented vertical slice (2026-10-14)

- Local Scout can now create a public invitation link for the lead. The restaurant chooses the customer preview, owner-dashboard experience, or a custom preview, selects a visual style and needs, and submits optional notes without needing a login.
- Invitation tokens are random bearer tokens, stored only as SHA-256 hashes, expire after seven days, and can be revoked by a platform owner. The public form receives only the restaurant's public-facing name/city/area/category; it does not receive CRM notes, contact lists, or owner/demo tokens.
- Submitting the form records the chosen preferences and an activity against the exact `local_business_id`, and sets the lead to `demo_booked` unless it is already closed as a partner/not-interested. It deliberately does not set `last_contacted_at`: an inbound request is not proof that NEXORA contacted the restaurant.
- Local Scout's **Prepare Demo for this request** action provisions the existing scoped customer-preview and owner-sandbox links, carries over the requested mode/style/features/notes, and stores `demo_workspace_id` on the invitation. The invitation becomes `demo_ready` and its public token closes, preventing a second workspace from being created from the same request.
- Invitation and demo pages send private/no-store, no-referrer, and no-index response headers. The customer invitation never receives an owner token; owner access remains a separate scoped bearer link with expiry/revocation.
- Local Scout can create a personalized demo workspace for a selected lead and records the request against that same lead.
- Separate random bearer links are issued for the customer preview and owner sandbox. Only SHA-256 token hashes are stored; links expire after seven days and can be revoked by a platform owner.
- The owner view can edit the demo menu and progress demo orders. The customer view can submit simulated orders.
- Demo orders are stored only in `demo_sandbox_orders`; they do not use the production `orders` or `order_items` tables and do not trigger production notifications, inventory, delivery, payment, or accounting workflows.
- If a lead is not linked to a restaurant with explicit menu-use permission (`permission_granted` or `licensed`), the preview starts with generic illustrative items. Real menu data is never copied into a preview just because a restaurant record is linked.
- Generated/copied messages are not sent automatically, and creating a preview does not mark the lead as contacted or change its sales stage.
- Required schema migrations: `2026_10_14_090000_create_demo_workspaces_tables.php` and `2026_10_14_100000_create_demo_invitations_table.php`. Both have been applied to the local `APP_ENV=local`, SQLite development database and exercised by the automated test suite. No production database was migrated or deployed; apply the same migrations in the deployment environment before enabling this flow there.

Operational note: raw demo tokens cannot be recovered after the link-creation response is lost because only hashes are persisted. Keep the generated links in the intended internal workflow; create a fresh workspace if the links are lost. Do not paste owner links into a customer message unless the owner intentionally needs sandbox controls.

## Outcome

Every restaurant lead should have one clear path from verification to a personalized demo, owner review, follow-up, and launch handoff. The client receives a real, usable preview—not a static marketing mockup—and can test the customer journey and the owner workflow.

## Demo modes

1. **Internal concept** — sample content clearly labeled as illustrative. Never describe sample prices or dishes as the restaurant's real menu.
2. **Personalized preview** — restaurant-approved logo, colors, menu items, prices, modifiers, service areas, and contact details. Unverified fields stay marked for review.
3. **Owner sandbox** — a separate test workspace for menu edits and simulated orders. Demo orders must not enter a restaurant's live orders, reports, delivery ledger, or accounting.
4. **Launch candidate** — client-approved content, owner access tested, order path tested, WhatsApp configuration verified, privacy/security checks passed, and rollback path documented.
5. **Published** — only after explicit approval of the exact production URL and content.

## Client journey

1. Open a personalized link on mobile.
2. See the restaurant name and a short, honest note that this is a preview prepared by NEXORA.
3. Browse categories, photos, variants, modifiers, and sample/live-approved prices.
4. Add items to the cart and complete a clearly labeled **test order** in sandbox mode.
5. Open the owner experience, edit an item/photo/price, and view the test order changing state.
6. Use **Request launch details** to show scope, required approvals, setup checklist, pricing range, and next steps. This must not publish or charge automatically.

## Lead pipeline

- Needs verification
- Verified — contact channel and source checked
- Ready for personalized demo
- First message prepared
- Contacted (only after a human actually sends it)
- Interested
- Demo scheduled / demo link shared
- Feedback received
- Proposal sent
- Client approved
- Launch preparation
- Published / active partner
- Follow-up / not interested

Do not mark a lead as contacted just because a message was generated or copied. Log the real channel, date, outcome, and next action after contact.

## First-contact message (Egyptian Arabic)

أهلًا بحضرتك، أنا من فريق NEXORA 👋

إحنا بنجهّز للمطاعم تجربة منيو رقمي باسم وهوية المطعم، بحيث العميل يتصفح الأصناف والأسعار بسهولة، وصاحب المطعم يقدر يدير المنيو ويتابع الطلبات من لوحة واحدة.

بدل ما أبعت لحضرتك صور أو كلام عام، نقدر نجهّز معاينة مخصصة لمطعمكم تشوفها من الموبايل وتجرب رحلة العميل ولوحة الإدارة بنفسك. أي أصناف أو أسعار غير مؤكدة هنعلّمها للمراجعة، ومش هننشر أي حاجة من غير موافقتكم.

يناسب حضرتك أبعتلك المعاينة هنا؟

## Message when demo is ready

أهلًا بحضرتك، جهّزنا معاينة أولية لتجربة المنيو الرقمي لمطعمكم: [رابط المعاينة]

جرّبها من الموبايل، وبعدها افتح «تجربة صاحب المطعم» لمراجعة إدارة الأصناف والطلبات. الطلبات داخل المعاينة تجريبية وليست طلبات تشغيل فعلية.

المعاينة للمراجعة فقط؛ الأسعار والأصناف والهوية النهائية تعتمد على تأكيد حضرتك. لو مناسبة، نراجع سوا نطاق التنفيذ، المدة، والتكلفة قبل أي نشر.

## Follow-up (only when appropriate)

أهلًا بحضرتك، حبيت أتابع معاك بخصوص معاينة NEXORA اللي بعتها. هل تحب نعدّل أي جزء في شكل المنيو أو طريقة عرض الأصناف؟ ولو الوقت مش مناسب حاليًا، مفيش أي مشكلة.

## Demo data rules

- Never scrape/reuse restaurant menu photos or full menu content for a client-facing demo without permission or an appropriate license.
- Mark illustrative content as “مثال توضيحي” and replace it only after confirmation.
- Do not put internal notes, lead lists, or contact details in a public bundle or URL.
- Demo tokens must be random, stored hashed, scoped to one restaurant/menu, revocable, and expiring.
- Sandbox orders must be isolated from live orders and all financial reports.
- Do not expose owner credentials or auto-create a weak/shared password. Use a secure invite/password setup flow.
- Never send a message, publish a listing, activate paid API billing, or deploy without explicit authorization.

## Launch gate

- [ ] Restaurant identity and contact details verified
- [ ] Permission to use logo/photos/menu confirmed
- [ ] Menu items/prices/variants/modifiers approved by owner
- [ ] Mobile customer menu tested
- [ ] Test order works and stays in sandbox
- [ ] Owner edits tested and reflected in preview
- [ ] Owner access and tenant isolation verified
- [ ] WhatsApp/order notification tested with an approved test number
- [ ] Demo expiry/revocation tested
- [ ] Client has approved exact final content and URL
- [ ] Production backup/rollback and support contact ready
- [ ] Explicit production publish approval recorded

## Definition of done

The system is ready when one lead can be selected in Local Scout, a separate sandbox demo can be provisioned, a personalized link and message can be generated, the customer and owner journeys can both be tested without touching live orders, and follow-ups are logged accurately. A generated/copied message is not proof of contact, and a successful build is not proof of a verified production deployment.
