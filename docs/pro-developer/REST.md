# WB Ad Manager Pro — REST API Reference

> **PRO feature reference.** This guide documents the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on. The endpoints below are only available when the Pro plugin is active on top of the free plugin.

Every public REST endpoint exposed by Pro. This list is the API surface a third-party developer (mobile app, headless frontend, integration) can rely on.

**Companion docs:** [`HOOKS.md`](HOOKS.md) for filters/actions, [`DEVELOPER-GUIDE.md`](DEVELOPER-GUIDE.md) for architecture overview.

## Conventions

**Namespace:** `wbam-pro/v1`
**Base URL:** `/wp-json/wbam-pro/v1/`

| HTTP method | Intent |
|---|---|
| `GET` | Read only — never mutates |
| `POST` | Create new resource |
| `PUT` | Full or partial update |
| `DELETE` | Delete resource |

### Authentication

All authenticated endpoints honor any auth method WordPress core accepts:

- **Cookie + `X-WP-Nonce` header** — same-origin web/admin clients (default for jQuery `wp.apiFetch`).
- **Application Passwords** — third-party scripts and mobile apps. `Authorization: Basic <base64>` over HTTPS.
- **JWT** — if a JWT plugin is active.

`permission_callback` returns `WP_Error(401)` (never bare `false`) so apps see structured 401 responses, never WordPress's generic "you cannot access this" string. The shared callback `\WBAM_Pro\Core\REST_Permissions::authenticated_read()` / `public_read($module)` / `public_write($module)` enforces module-toggle gating and the WP_Error envelope consistently.

### Permission scopes (auth column below)

| Scope | Meaning |
|---|---|
| `public` | No auth required. Module must be enabled. |
| `is_user_logged_in` | Any logged-in WP user. |
| `advertiser scope` | User is logged in AND has an advertiser record (`wp_wbam_advertisers`). Endpoint reads/writes are scoped to that advertiser's own data. Admins see all. |
| `owner check` | User must own the resource (advertiser_id matches). Admins bypass. |
| `manage_options` | WP admin capability. |
| `thread participant` | User must be one of the messaging thread participants. |
| `rate-limited public` | Public, but throttled via `wp_wbam_rate_limits`. |

### Slug substitution

Some routes use `{slug}` in the URI — this is the configurable Classifieds base slug (default `classifieds`, can be set in `Settings → Classifieds`). Apps should resolve it once via `GET /wp-json/wbam-pro/v1/settings/app-config` (when implemented — see roadmap) or hard-code per-customer.

`{base}` in the credits routes refers to the Credits SDK's own namespace prefix and is not part of `wbam-pro/v1` — those endpoints are documented here for completeness because they're what most clients want for top-up flows. Their canonical home is the SDK.

### Response envelope (list endpoints)

Every list endpoint returns:

```json
{
  "<resource>": [ ... ],
  "total": 128,
  "pages": 7,
  "has_more": true
}
```

- `<resource>` matches the route (`ads`, `classifieds`, `campaigns`, `transactions`, etc.).
- `has_more` uses the formula `(offset + count(items)) < total` — never `count(items) === per_page` (that would break on the last partial page).
- `per_page` is capped at 100 server-side regardless of request value. Default 12.

### Single-resource fields

Every single-resource response includes:

- `id` — integer
- `created_at` — ISO 8601 UTC
- `updated_at` — ISO 8601 UTC

Never `date` as a field name.

### Error contract

```json
{
  "code": "wbam_classified_not_found",
  "message": "Classified not found.",
  "data": {
    "status": 404,
    "classified_id": 42
  }
}
```

| HTTP | When |
|---|---|
| `400` | Validation failure. `data.errors` carries per-field errors. |
| `401` | Authentication required. |
| `403` | Authenticated but not authorized (e.g. owner-check failed). |
| `404` | Resource not found. |
| `429` | Rate-limited. `data.retry_after` seconds. |
| `500` | Server error. Log line written via `Logger::error_from_exception()` (see [DEVELOPER-GUIDE.md](DEVELOPER-GUIDE.md#logging)). |

### Response filters (extension surface)

Every response is filtered before send. Use these to inject app-specific fields without forking:

- `wbam_pro_rest_prepare_advertiser` — `(array $data, Advertiser $advertiser, WP_REST_Request $request)`
- `wbam_pro_rest_prepare_campaign` — `(array $data, Campaign $campaign, WP_REST_Request $request)`
- `wbam_pro_rest_prepare_classified_user` — public-shape classified payload
- `wbam_pro_rest_prepare_classified_admin` — admin-shape (full meta + user/advertiser block)
- `wbam_pro_rest_prepare_package` — `(array $data, Package $package)`

See [HOOKS.md → REST response filters](HOOKS.md#rest-response-filters) for the full list.

---

## Endpoints

### Advertiser portal (self-service)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET`, `POST` | `/advertiser/profile` | `is_user_logged_in` | Get / update current advertiser profile |
| `GET` | `/advertiser/stats` | advertiser scope | Performance stats (impressions, clicks, CTR, spend) |
| `GET` | `/advertiser/wallet` | advertiser scope | Wallet balance + recent activity |
| `GET` | `/advertiser/transactions` | advertiser scope | Wallet transactions (paginated) |
| `GET` | `/advertiser/ads` | advertiser scope | List advertiser-owned ads |

### Campaigns (advertiser-owned)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET`, `POST` | `/campaigns` | advertiser scope | List / create campaigns |
| `GET`, `POST`, `DELETE` | `/campaigns/{id}` | owner check | Single campaign CRUD |
| `POST` | `/campaigns/{id}/pause` | owner check | Pause active campaign |
| `POST` | `/campaigns/{id}/resume` | owner check | Resume paused campaign |
| `POST` | `/campaigns/{id}/duplicate` | owner check | Duplicate campaign (with new draft status) |
| `GET` | `/campaigns/{id}/stats` | owner check | Per-campaign analytics |

### Packages (purchase catalog)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/packages` | public | List active packages (for store display) |
| `GET` | `/packages/{id}` | public | Get single package details |

### Classifieds (public)

> `{slug}` is the Classifieds base slug, defaulting to `classifieds`. Configurable via plugin settings.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/{slug}` | public | List published classifieds |
| `POST` | `/{slug}` | `is_user_logged_in` | Create new classified |
| `GET` | `/{slug}/{id}` | public | View single classified |
| `GET` | `/{slug}/categories` | public | List classified categories |
| `GET` | `/{slug}/trending` | public | Trending classifieds |
| `POST` | `/{slug}/{id}/contact` | rate-limited public | Submit contact-seller form |

### Classifieds (owner-scoped)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET`, `POST` | `/my/{slug}` | `is_user_logged_in` | User's own classifieds list / create |
| `GET`, `POST`, `DELETE` | `/my/{slug}/{id}` | owner check | User's own classified CRUD |
| `POST` | `/my/{slug}/{id}/sold` | owner check | Mark listing as sold |
| `POST` | `/my/{slug}/{id}/renew` | owner check | Renew expiring listing |
| `POST` | `/my/{slug}/{id}/upgrade` | owner check | Upgrade to featured / highlighted / bump |
| `GET` | `/{slug}/{id}/analytics` | owner check OR admin | Per-listing analytics |
| `POST` | `/{slug}/bulk` | owner check per item | Bulk action on multiple owned listings |

### Reviews

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/reviews` | public | List reviews for a seller |
| `POST` | `/reviews` | `is_user_logged_in` | Submit review |

### Messaging

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET`, `POST` | `/messages/threads` | `is_user_logged_in` | List / create message threads |
| `GET` | `/messages/threads/{id}` | thread participant | Get thread + message history |
| `POST` | `/messages/threads/{id}/reply` | thread participant | Reply to thread |
| `PUT` | `/messages/threads/{id}/read` | thread participant | Mark thread as read |

### Rotation

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/rotation/share` | advertiser scope | View rotation share % across the advertiser's ads |
| `GET` | `/rotation/log` | advertiser scope | Recent rotation events |
| `GET` | `/rotation/export` | advertiser scope | Export rotation log as CSV |

### Analytics (advertiser-scoped)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/analytics/overview` | advertiser scope or admin | Aggregate impressions/clicks/CTR/visitors-reached over a date range |
| `GET` | `/analytics/ads/{id}` | owner check | Per-ad analytics with placement breakdown |
| `GET` | `/analytics/chart` | advertiser scope | Time-series chart data (`ad_id`, `campaign_id`, `metric` filters) |
| `GET` | `/analytics/top-ads` | advertiser scope | Top-performing ads by impressions |
| `GET` | `/analytics/top-placements` | advertiser scope | Top placements by impressions |
| `GET` | `/analytics/geo` | advertiser scope | Country-level traffic breakdown |
| `GET` | `/analytics/devices` | advertiser scope | Device-type breakdown |
| `GET` | `/analytics/export` | advertiser scope | Export analytics CSV |

The `/analytics/overview` response (since 1.6.0):

```json
{
  "impressions": 158196,
  "clicks": 4805,
  "ctr": 3.04,
  "unique_impressions": 110578,
  "unique_clicks": 3742,
  "visitors_reached": 4612,
  "avg_ads_per_visitor": 23.97,
  "revenue": 733.20,
  "active_ads": 15
}
```

`visitors_reached` and `avg_ads_per_visitor` are nullable when the period has no raw event rows (post-aggregation, retention dropped). Apps should render `—` rather than `0` in that case. See `pro-internal/CRON.md` and the dashboard equivalent for the math.

### Custom fields (admin-only)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET`, `POST` | `/custom-fields` | `manage_options` | List / create custom field definitions |
| `GET`, `POST`, `DELETE` | `/custom-fields/{id}` | `manage_options` | Single custom field CRUD |
| `POST` | `/custom-fields/reorder` | `manage_options` | Reorder custom fields |

### Admin endpoints

> `{slug}` placeholder same as the public Classifieds routes.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET`, `POST` | `/admin/advertisers` | `manage_options` | List / create advertisers |
| `GET`, `POST`, `DELETE` | `/admin/advertisers/{id}` | `manage_options` | Single advertiser CRUD |
| `GET`, `POST` | `/admin/packages` | `manage_options` | List / create packages |
| `GET`, `POST`, `DELETE` | `/admin/packages/{id}` | `manage_options` | Single package CRUD |
| `POST` | `/admin/packages/{id}/duplicate` | `manage_options` | Duplicate package |
| `POST` | `/admin/packages/reorder` | `manage_options` | Reorder packages by drag-drop |
| `POST` | `/admin/packages/create-defaults` | `manage_options` | Bootstrap default package set |
| `GET` | `/admin/{slug}` | `manage_options` | List all classifieds |
| `GET`, `POST`, `DELETE` | `/admin/{slug}/{id}` | `manage_options` | Single classified CRUD |
| `POST` | `/admin/{slug}/{id}/approve` | `manage_options` | Approve pending classified |
| `POST` | `/admin/{slug}/{id}/reject` | `manage_options` | Reject pending classified |
| `GET` | `/admin/campaigns` | `manage_options` | All campaigns (cross-advertiser) |
| `POST` | `/admin/campaigns/{id}/approve` | `manage_options` | Approve pending campaign |
| `POST` | `/admin/campaigns/{id}/reject` | `manage_options` | Reject pending campaign |
| `GET` | `/admin/reviews` | `manage_options` | Review moderation queue |
| `PUT` | `/admin/reviews/{id}` | `manage_options` | Approve / reject review |
| `GET` | `/admin/rotation/placement` | `manage_options` | Placement rotation diagnostics |
| `GET`, `POST` | `/admin/rotation/settings` | `manage_options` | Get / update rotation settings |

### Credits SDK

These endpoints belong to the bundled [Wbcom Credits SDK](https://github.com/vapvarun/wbcom-credits-sdk) and are exposed under its own namespace (`{base}` resolves to the SDK's prefix, typically `/wp-json/wbcom-credits/v1`). Documented here because most app integrations want them for the top-up flow.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `GET` | `/{base}/balance` | `is_user_logged_in` | Current credit balance |
| `GET` | `/{base}/history` | `is_user_logged_in` | Ledger history (paginated) |
| `POST` | `/{base}/topup` | `is_user_logged_in` | Top-up via configured adapter (Woo / PMPro / etc.) |

---

## Worked example — submit and approve an ad classified

```bash
# 1. Authenticate (Application Password)
AUTH="-u 'admin:abcd efgh ijkl mnop qrst uvwx'"

# 2. Create a classified
curl $AUTH -X POST 'https://example.com/wp-json/wbam-pro/v1/my/classifieds' \
  -H 'Content-Type: application/json' \
  -d '{"title":"Macbook Pro","price":1200,"category_id":42}'
# → 201 { "id": 56, "status": "pending", "created_at": "...", "updated_at": "..." }

# 3. Admin approves
curl $AUTH -X POST 'https://example.com/wp-json/wbam-pro/v1/admin/classifieds/56/approve'
# → 200 { "id": 56, "status": "active", ... }

# 4. Track impressions reach the analytics window
curl $AUTH 'https://example.com/wp-json/wbam-pro/v1/analytics/overview?start_date=2026-05-01&end_date=2026-05-07'
# → { "impressions": ..., "visitors_reached": ..., "avg_ads_per_visitor": ... }
```

---

## Stability and versioning

**Stable since:** 1.5.0 unless noted.

**Additive changes are non-breaking** — new optional response fields (e.g. `visitors_reached` added in 1.6.0), new optional request parameters, new endpoints. Apps reading unknown fields should ignore them.

**Breaking changes** require a new namespace (`wbam-pro/v2`). The plugin will run both for at least one minor cycle before the v1 endpoints sunset.

**Pre-stable endpoints** are tagged inline. None at this writing.

---

## Audit cross-reference

This file is the public-API counterpart to [`HOOKS.md`](HOOKS.md). Together they let `wp-plugin-onboard --refresh` distinguish "intentional public surface" from "actually orphaned dead code" — anything listed here or in HOOKS.md is expected to be referenced from outside the plugin and should not be flagged as an orphan.

Update this file in the same PR that adds, deprecates, or breaks an endpoint. The audit pipeline diff-checks the route inventory in `audit/manifest.json` against this doc on every release.
