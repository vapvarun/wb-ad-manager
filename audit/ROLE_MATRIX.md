# WB Ads Rotator (FREE) — Role × Capability Matrix

**Generated**: 2026-04-29
**Source**: [`audit/manifest.json`](manifest.json)

The plugin uses **only WP core capabilities** — no custom caps. The CPT `wbam-ad` uses `capability_type: post`, so any role with `edit_posts` can author ads, but settings/admin pages require `manage_options`.

Legend: **C**=Create, **R**=Read, **U**=Update, **D**=Delete, **—**=No access.

## Capability gates

| Capability | Where checked |
|---|---|
| `manage_options` | All settings, all admin REST writes, settings/display REST, links/partnerships REST, all admin pages |
| `edit_posts` (default) | Ads CPT visibility (since `capability_type: post`) |
| `is_admin()` | `wbam_dismiss_notice`, `wbam_dismiss_pointer` AJAX |
| (none — public) | All `__return_true` REST routes; all `nopriv` AJAX |

## Role matrix — admin features

| Feature | Administrator | Editor | Author | Subscriber |
|---|---|---|---|---|
| Ads CPT (manage) | CRUD | CRUD | CRUD (own) | — |
| Settings page | CRUD | — | — | — |
| Setup Wizard | CRUD | — | — | — |
| Help & Docs | R | — | — | — |
| Upgrade to Pro page | R | — | — | — |
| Links admin | CRUD | — | — | — |
| Link Categories | CRUD | — | — | — |
| Partnerships admin (approve/reject) | CRUD | — | — | — |
| `wbam_settings` option | RU | — | — | — |

## Role matrix — REST endpoints

| Endpoint | Anonymous | Logged-in (any) | Administrator |
|---|---|---|---|
| `GET /wbam/v1/ads` | R | R | R |
| `GET /wbam/v1/ads/serve` | R | R | R |
| `GET /wbam/v1/ads/placements` | R | R | R |
| `GET /wbam/v1/ads/types` | R | R | R |
| `POST /wbam/v1/ads/track` | C (rate-limited) | C | C |
| `POST /wbam/v1/ads` | — | — | C |
| `GET/PUT/DELETE /wbam/v1/ads/{id}` | — | — | RUD |
| `GET /wbam/v1/ads/{id}/stats` | — | — | R |
| `POST /wbam/v1/ads/{id}/duplicate` | — | — | C |
| `GET /wbam/v1/analytics/overview` | — | — | R |
| `GET /wbam/v1/analytics/ads/{id}` | — | — | R |
| `GET /wbam/v1/analytics/daily` | — | — | R |
| `POST /wbam/v1/analytics/track` | C (rate-limited) | C | C |
| `GET/POST /wbam/v1/links` | — | — | RC |
| `GET/POST /wbam/v1/links/categories` | — | — | RC |
| `GET/PUT/DELETE /wbam/v1/links/{id}` | — | — | RUD |
| `GET /wbam/v1/links/{id}/stats` | — | — | R |
| `POST /wbam/v1/links/{id}/track` | C (rate-limited) | C | C |
| `GET /wbam/v1/partnerships` | — | — | R |
| `PUT /wbam/v1/partnerships/{id}` | — | — | U |
| `GET/PUT /wbam/v1/settings` | — | — | RU |
| `GET/PUT /wbam/v1/settings/display` | — | — | RU |

## Role matrix — AJAX handlers

| Action | Anonymous | Logged-in | Administrator |
|---|---|---|---|
| `wbam_track_click` | C | C | C |
| `wbam_email_capture` | C | C | C |
| `wbam_track_link_click` | C | C | C |
| `wbam_submit_partnership` | C | C | C |
| `wbam_dismiss_notice` | — | (admin only) | U |
| `wbam_dismiss_setup` | — | — | U |
| `wbam_dismiss_pointer` | — | (admin only) | U |

## Role matrix — Shortcodes

All shortcodes are **public** (no auth required) — they render content based on data the admin has populated:

| Shortcode | Renders |
|---|---|
| `[wbam_ad id=… placement_id=…]` | Single ad by ID/placement |
| `[wbam_ads placement_id=… limit=…]` | Multiple ads for placement |
| `[wbam_link id=… slug=…]` | Single link with cloaked URL |
| `[wbam_links category=… limit=…]` | Filtered list of links |
| `[wbam_link_url id=… slug=…]` | Bare cloaked URL (no markup) |
| `[wbam_partnership_inquiry]` | Public partnership submission form |

## Public surface area summary

The plugin's **public attack surface** consists of:
- 5 public REST GET routes (read-only listings)
- 3 public REST POST routes (rate-limited tracking)
- 4 public AJAX endpoints with `nopriv` variants (rate-limited tracking + form submission)
- 6 shortcodes (server-side rendering, no privileged data exposed)
- 1 link cloaker `init` interceptor (`/go/{slug}` → 302)

All public-write endpoints are nonce-gated AND IP-rate-limited. No CRUD on stored data is exposed without `manage_options`.
