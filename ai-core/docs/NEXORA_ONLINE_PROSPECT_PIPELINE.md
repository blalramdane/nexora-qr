# NEXORA QR — Online Restaurant Prospect Pipeline

## Purpose

This workflow helps the NEXORA team discover a restaurant, verify its official website, find a public menu link, extract a private menu draft, prepare a scoped demo, and prepare an outreach message. It does **not** automatically publish restaurant data or send a message.

## Operator workflow

1. Sign in to Local Scout with a Platform Owner account.
2. Search a city and restaurant category in the Google Places panel. Results remain an ephemeral preview and are shown with Google attribution.
3. Open the official website from the result and verify that the website actually belongs to the restaurant.
4. Choose **اعتماد الموقع وتجهيز Lead**. NEXORA reads the official website, derives the displayed name from that site, records the confirmed official website and the reusable Place ID, and creates a private lead in `draft` / `needs_verification` state. Google-supplied name, address, and phone fields are not persisted from the Places response.
5. NEXORA looks for likely menu links on the same HTTPS host and checks the site's `robots.txt`. Select the correct candidate rather than assuming the first link is the menu.
6. HTML menu text and direct JPG/PNG/WEBP menu images can be sent to the configured AI extraction provider. Review every category, item name, and price before saving. Blank/uncertain prices must be corrected by a human.
7. After the review is saved, click **جهّز Demo من المنيو ده**. The reviewed snapshot is confined to a private Demo Workspace and explicitly remains subject to the restaurant owner's confirmation. No live menu record is changed.
8. Use the existing copy-message action to prepare the outreach message. A human chooses the channel and sends it manually, then records the actual contact and follow-up in Local Scout.

## Required local environment configuration

The following variables are optional and intentionally left empty/disabled in `.env.example`:

```dotenv
GOOGLE_PLACES_API_KEY=
GOOGLE_PLACES_ENABLED=false
OPENAI_API_KEY=
OPENAI_MENU_SCAN_MODEL=gpt-4o-mini
OPENAI_BASE_URL=https://api.openai.com/v1
```

- Put credentials in the local/deployment environment only. Never commit secret values or send them to the browser.
- Google Places search stays disabled until a valid server-side key exists and `GOOGLE_PLACES_ENABLED=true` is explicitly chosen. Places API requests require an enabled billing account and can incur charges; configure quota and budget alerts before enabling it. The current search uses a field mask; do not replace it with `*` in production.
- Set `OPENAI_API_KEY` to enable automatic extraction. Without it, NEXORA stores the blocker status and does not fabricate menu items or prices.
- `OPENAI_MENU_SCAN_MODEL` must name a model available to the configured API account that supports image input and JSON output. Verify this against the current OpenAI API documentation before changing it.

## Source and privacy rules

- Google Places results are transient. Persist the Place ID for deduplication; derive the saved restaurant identity from the official website after operator confirmation, not from cached Places fields. Honor Google's display/attribution and caching rules.
- Only fetch public HTTPS websites. The menu discovery service denies private/local hosts, rejects redirects, limits response sizes, and checks same-host links and `robots.txt`. It intentionally does not crawl unrelated domains or social networks.
- Only use public menu material for internal research and a private review draft. Names/prices are unverified facts until reviewed; do not copy menu photos, logos, creative descriptions, or publish the draft without the restaurant's permission or appropriate rights.
- `menu_rights_status` remains `not_checked` unless a human confirms permission or a license. A prospect menu draft is not proof of authorization to publish.
- No message, WhatsApp request, paid API enablement, public directory listing, production order, or deployment is triggered by this workflow.

## Current supported content types and limitations

| Source | Current behavior |
|---|---|
| Official website HTML | Discover candidate links; extract visible text if a menu page is selected |
| Direct JPG/JPEG, PNG, WEBP | Send to configured Vision model for extraction |
| PDF menu | Candidate can be found, but must currently be rendered/uploaded as page images using the existing menu scanner before extraction |
| Login-only or blocked source | No automated bypass; use an owner-provided menu or add the source manually |
| Google Places key absent or disabled | Search is disabled; no request or API cost is incurred |
| OpenAI key absent | Search/link discovery can run, but AI item/price extraction reports `awaiting_ai_config` |

## Verification checklist

- [ ] Search returns temporary Google results with attribution when Places is configured.
- [ ] Clicking the official-site intake creates one private prospect and one research row.
- [ ] Repeating the same Place ID and unchanged site content does not duplicate the business/research row.
- [ ] Only same-host, robots-permitted official-site menu links are offered.
- [ ] AI extraction uses the configured model, and unconfigured/failed providers do not invent results.
- [ ] Item names/prices are reviewed before a demo is created.
- [ ] The demo snapshot is linked to its research row and stays in the sandbox.
- [ ] No message is sent, no public listing is published, and no production business/menu/order is modified.
- [ ] Tests and frontend build pass in the target workspace.
