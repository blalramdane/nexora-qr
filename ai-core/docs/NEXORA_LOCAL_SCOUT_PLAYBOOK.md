# NEXORA Local — Scout Research Playbook

## Goal

Build a trustworthy, useful restaurant directory for El Zarqa and Damietta, then turn verified owners into NEXORA QR/POS prospects. Coverage alone is not success: verified records, owner responses, partner activations, and qualified sales conversations are the measures that matter.

## Research workflow

1. Search one city and one category at a time (restaurants, cafes, bakeries, grills, seafood, fast food, family dining).
2. Use an explicitly permitted provider/API or a business-owned public source. Do not scrape Google Maps pages, bypass access controls, or harvest personal contact details.
3. Treat provider search results as discovery leads, not as permission to republish their full contents. Google Places content is preview-only in the current implementation; verify the business website or owner-managed social page and manually record facts supported by that source.
4. Check duplicates before adding a record: normalized business name + city, phone when business-published, website domain, and source URL. Do not merge based on name alone when branches may exist.
5. Keep new records as drafts. A record must have a source URL, city, category, and a reviewer check before public publication. Mark menus as `unknown`, `available`, `missing`, or `needs_review`; never invent menu items, prices, opening hours, or delivery availability.
6. Use the official source URL to confirm name, business-owned phone, website, and menu URL. Do not copy menu text, photos, or logos into NEXORA without permission or a clear license.
7. Contact businesses respectfully through business-published channels, explain the directory, offer correction/removal, and ask permission before claiming a page as an official partner or displaying partner-only benefits.
8. Track the next action and outcome: needs verification → verified listing → owner contacted → owner interested → demo booked → proposal → activated partner / not interested.

## Quality checklist before publishing

- [ ] Name and city confirmed from a business-owned or otherwise reliable source.
- [ ] Correct branch and area confirmed; no duplicate branch merged accidentally.
- [ ] Source URL recorded and reachable.
- [ ] Phone, website and menu links are business-published and checked.
- [ ] No unlicensed images or copied menu/prices.
- [ ] Listing is clearly not an official NEXORA partner unless the owner has agreed and verification is complete.
- [ ] Contact/removal correction path is available.

## Provider policy

### Google Places API

- Disabled by default. No API key is required to use manual research.
- Before enabling: review the current Google Maps Platform terms, create public Terms of Use and Privacy Policy, implement required Google Maps attribution, configure billing limits/budget alerts, and get explicit owner approval for any potentially billable call.
- The current integration returns results only to the active search response, shows `Google Maps` attribution, and does not persist the returned content. Store only independently verified business details from an authorized source in the NEXORA directory.
- Do not assume a configured API key means approval to spend.

### OpenStreetMap public Nominatim

- Do not use the public Nominatim endpoint for bulk discovery, systematic area-wide POI downloads, or recurring automated lead collection. Its policy prohibits systematic queries/downloads of all POIs and limits use; use a properly licensed data extract or an approved provider for broad coverage.
- Any OSM data used must retain appropriate attribution and comply with the ODbL license.

## Operating cadence (no paid tools required)

- Research in small batches of 10–20 businesses per city/category.
- Review every record before publication.
- Contact a small number of relevant owners with personalized outreach; do not bulk-message or repeatedly contact non-responders.
- Weekly review: records researched, verified rate, duplicates, menu coverage, owners contacted, positive replies, demos booked, partner activations, corrections/removals, and spend (must remain zero unless explicitly approved).

## Local skill prompt

When acting as the NEXORA Local Scout, prioritize correctness and permission over volume. Return a structured batch with: business name, city/area, category, official source URL, official website/menu URL if available, business-published phone if available, evidence notes, confidence (`high`/`medium`/`low`), duplicate candidates, and recommended next action. Never invent missing fields. Keep low-confidence records out of the public directory. Never make a paid API call, enable billing, send messages, or publish unreviewed records without authorization.
