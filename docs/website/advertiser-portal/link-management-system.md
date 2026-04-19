# Link Management System

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

WB Ad Manager Pro extends the free WB Ad Manager link module with advanced click tracking, keyword auto-linking, link health monitoring, CSV import, and link groups. These features are part of the **Links Pro** module.

**Enable at:** Pro Settings → Modules → Links Pro

---

## Overview

| Feature | Description |
|---------|-------------|
| Advanced Click Tracking | Records device type, country, referrer, and IP hash per click |
| Keyword Auto-Linking | Automatically replaces defined keywords in post content with tracked affiliate links |
| Link Health Checker | Periodically checks all links for broken URLs, redirects, and HTTP errors |
| CSV Import | Bulk-import links and keywords from a spreadsheet |
| Link Groups | Organise links into named groups for easier management |

---

## Creating Links

Links are created and managed in the free WB Ad Manager plugin's Links section. The Pro module extends these links with additional tracking and management features.

When a link is clicked, the `wbam_link_clicked` action fires and the Pro module's `Link_Tracker` records detailed click data automatically.

---

## Advanced Click Tracking

The `Link_Tracker` class hooks into the free plugin's `wbam_link_clicked` and `wbam_before_link_redirect` actions.

**Data recorded per click:**

- Link ID
- Timestamp
- Device type (desktop, mobile, tablet)
- Country (from IP geolocation)
- Referrer URL
- Anonymised IP hash (daily rotating salt)
- Browser
- User ID (if logged in)

Click data is stored in two tables:

| Table | Purpose |
|-------|---------|
| `wbam_link_clicks_daily` | Aggregated daily click totals per link |
| `wbam_link_clicks_detailed` | Full event-level click records |

Click charts and per-link analytics are accessible from the Links admin screen.

---

## Keyword Auto-Linking

The `Keyword_Linker` class scans post content on the frontend and replaces defined keywords with tracked links automatically. It is optimised for sites with 1,000–5,000+ posts.

### Adding Keywords to a Link

1. Open a link in the admin edit screen.
2. Go to the **Keywords** section.
3. Add one or more keywords and configure each keyword's options.

### Keyword Options

| Option | Default | Description |
|--------|---------|-------------|
| Keyword | — | The text to match in content |
| Case Sensitive | Off | Match exact capitalisation |
| Whole Word | On | Only match the keyword as a standalone word, not inside other words |
| Max Replacements | 3 | Maximum times this keyword is replaced per page load |
| Priority | 0 | Higher priority keywords are processed first when multiple keywords overlap |
| Post Types | (all) | Restrict auto-linking to specific post types (JSON-encoded array) |
| Exclude Posts | — | Comma-separated post IDs to never auto-link in (JSON-encoded array) |
| Status | `active` | `active` or `inactive` |

### Performance

Keywords are cached using WordPress transients with a 1-hour expiration (`wbam_keywords_cache`). Modifying keywords clears the cache automatically.

Content inside links (`<a>` tags), headings, scripts, style blocks, and other protected elements is excluded from keyword replacement to prevent nesting links or breaking existing markup.

---

## Link Health Checker

The `Link_Health_Checker` class periodically verifies that tracked links resolve correctly.

**Health check behaviour:**

- Checks are run in batches of 20 links at a time (`DEFAULT_BATCH_SIZE = 20`).
- Maximum execution time per batch is 25 seconds (`MAX_EXECUTION_TIME = 25`).
- The same URL will not be re-checked more often than once per hour (`MIN_RECHECK_INTERVAL = 3600` seconds).
- A 500ms delay is enforced between requests to the same domain (`DOMAIN_DELAY_MS = 500`) to avoid rate limiting.

**Health statuses recorded:**

| Status | Meaning |
|--------|---------|
| `ok` | URL responded with HTTP 200 |
| `redirect` | URL redirects to another location |
| `broken` | URL returned a 4xx or 5xx error |
| `timeout` | Request timed out |

Health data is stored in `wbam_link_health`. The Links admin list shows a health status badge per link.

---

## CSV Import

The `Link_Importer` class allows bulk creation of links and keywords from a CSV file.

**To import:**

1. Go to **WB Ads → Links → Import**.
2. Upload a CSV file.
3. Review the import preview.
4. Click **Import**.

**Import results:**

| Result | Meaning |
|--------|---------|
| Imported | Successfully created |
| Skipped | Already exists (duplicate slug detected) |
| Errors | Row-level validation failures |

The importer extends the free plugin's `Link_Manager` and uses `Link_Manager_Pro` for any Pro-specific fields (keywords, groups).

---

## Link Groups

Links can be organised into named groups stored in `wbam_link_groups`. Groups allow filtering and batch operations in the admin.

**Managing groups:**

- Create groups from the Links admin screen.
- Assign a link to a group when creating or editing it.
- Filter the links list by group.

---

## Database Tables

| Table | Description |
|-------|-------------|
| `wbam_link_keywords` | Keyword-to-link mappings for auto-linking |
| `wbam_link_clicks_daily` | Aggregated daily click counts |
| `wbam_link_clicks_detailed` | Full click event records |
| `wbam_post_links` | Cached map of which links appear in which posts |
| `wbam_link_health` | Health check results per link |
| `wbam_link_groups` | Link group definitions |

---

## Shortcodes

These shortcodes are provided by the free WB Ad Manager plugin and work with tracked links created in the free or Pro module.

| Shortcode | Description |
|-----------|-------------|
| `[wbam_link id="123"]` | Display a single tracked link |
| `[wbam_links category="affiliate"]` | Display a list of links filtered by group/category |
| `[wbam_link_url id="123"]` | Output just the cloaked URL for use in custom HTML |
| `[wbam_partnership_inquiry]` | Partnership request form for visitors (free Links module) |

---

## Advertiser Portal — Links Tab

When the free Links module's `Partnership_Form` class is active, the advertiser dashboard shows a **Links** tab. This tab displays link partnership requests submitted by site visitors through `[wbam_partnership_inquiry]`.

Advertisers can review, accept, or decline partnership requests from within their dashboard without needing admin access.
