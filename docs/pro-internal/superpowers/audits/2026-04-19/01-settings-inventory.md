# Pass 1 — Settings Inventory Audit

**Date:** 2026-04-19  
**Audited Plugins:**
- WB Ad Manager Pro (1.5.0, HEAD 90436ec)
- WB Ads Rotator with Split Test (2.8.0, HEAD 9f38b51)

**Scope:** All option storage keys, post/user meta keys used as settings, and register_setting() calls.

---

## Executive Summary

| Metric | Count |
|--------|-------|
| **Total unique settings keys** | 47 |
| **Pro plugin keys** | 31 |
| **Free plugin keys** | 23 |
| **Overlap (Free & Pro)** | 7 |
| **DEAD-READ** (read, never written) | 3 |
| **DEAD-WRITE** (written, never read) | 2 |
| **UNSANITIZED** (update_option without registered sanitizer) | 12 |
| **DUPLICATE-KEY** (same logic, different names) | 2 |
| **HARDCODED-DEFAULT** (3+ files with same default) | 5 |
| **FREE-PRO-OVERLAP** (defined in both) | 7 |

---

## P0 Findings (Release Blockers)

| Finding | Severity | Count | Details |
|---------|----------|-------|---------|
| **UNSANITIZED writes** | P0 | 12 | `update_option()` called in user-reachable code without sanitizer registered. Affects: `wbam_pro_settings` (Pro Admin), `wbam_settings` (Free Admin), individual page options, credit purchase URL. |
| **FREE-PRO-OVERLAP** | P0 | 7 keys | `wbam_settings`, `wbam_demo_data_ids`, `wbam_setup_complete`, `wbam_setup_dismissed`, `wbam_email_submissions`, `wbam_currency`, `wbam_link_prefix` exist in BOTH plugins. Free writes `wbam_settings[currency]` and `wbam_link_prefix` independently; Pro reads from Free's keys. Causes migration debt if Free uninstalled. |
| **DEAD-WRITE** | P0 | 2 | `wbam_pro_legacy_demo_pages_exist` written but never read (DB 2.2.0 marker, orphaned). `wbam_ab_testing_db_version` set but only checked once at install. |

---

## P1 Findings (Inconsistencies)

| Finding | Severity | Count | Details |
|---------|----------|-------|---------|
| **DEAD-READ** | P1 | 3 | `wbam_format_matching_enabled` — read in 3 places, never written by Pro. Only written by free's Next-Step banner. If Free disabled, setting orphans. Also `wbam_pro_rest_legacy_routes` read once, never written. `wbam_bump_price` read from Free settings but Free never writes it (inherited from older version). |
| **DUPLICATE-KEY** | P1 | 2 | `wbam_currency` (Free writes) vs. Pro's internal `currency` inside `wbam_pro_settings` blob — two sources of truth. `link_cloak_prefix` stored both in Free's `wbam_settings[link_cloak_prefix]` and directly as `wbam_link_prefix` option. |
| **HARDCODED-DEFAULT** (3+ files) | P1 | 5 | `'USD'` currency default appears in 4 files. Page IDs default to `0` in 5 locations. Link prefix defaults to `'go'` in 3 files. Admin email defaults to `get_option('admin_email')` in 8+ templates (not a setting, but hardcoded dependency). |

---

## P2 Findings (Dev-Experience Gaps)

| Finding | Severity | Count | Details |
|---------|----------|-------|---------|
| **No defaults in DB schema** | P2 | 12 | Module-specific settings (`wbam_pro_classifieds_settings`, `wbam_pro_email_settings`, `wbam_pro_geolocation_settings`, `wbam_pro_payment_settings`, `wbam_pro_revenue_settings`) are created on-demand with empty arrays; no schema or documented defaults. Installer seeds them, but no migration guard. |
| **Incomplete register_setting** | P2 | 2 | `wbam_settings` (Free) and `wbam_pro_settings` (Pro) both register WITH sanitizer, but 12 individual page options (`wbam_page_*`) use `update_option()` directly, bypassing Settings API. |
| **Post/User meta not grouped** | P2 | 20+ meta keys | Ad meta keys (`_wbam_ad_*`, `_wbam_placements`, etc.) are all individual; no meta table. User meta `_wbam_advertiser_id` is singular; no bulk querying helper. |

---

## Full Settings Table

| Key | Plugin | Storage | Default | Sanitizer | Written By | Read By | Type | Notes |
|-----|--------|---------|---------|-----------|------------|---------|------|-------|
| `wbam_settings` | Free | options | `array()` | `sanitize_settings()` callback | `class-settings.php:90` | `Settings_Helper::get()` used 20+ places | Settings blob | Main Free settings container. Sanitizer validates all keys. |
| `wbam_pro_settings` | Pro | options | `array()` | `sanitize_settings()` callback | `class-pro-admin.php:536-540` | `Settings_Helper::get()`, read 50+ places | Settings blob | Main Pro settings container. Merged across tabs. |
| `wbam_pro_classifieds_settings` | Pro | options | `array('from_email' => admin_email)` | None | `class-pro-admin.php:3663` | `Settings_Helper::get_classifieds()` 5 places | Settings blob | Classifieds module config. No sanitizer on direct update. |
| `wbam_pro_email_settings` | Pro | options | `array()` | None | `class-pro-admin.php:4283` | Geolocation_Manager reads (1 place) | Settings blob | Email notification config. No sanitizer. |
| `wbam_pro_geolocation_settings` | Pro | options | `array()` | None | `class-pro-admin.php:4212` | `Geolocation_Manager::get_settings()` (1 place) | Settings blob | Geolocation provider config. No sanitizer. |
| `wbam_pro_payment_settings` | Pro | options | `array()` | None | `class-installer.php:1593` | `Settings_Helper::get_payment()` (0 reads found) | Settings blob | DEAD-READ: Written at install, never read. Orphaned. |
| `wbam_pro_revenue_settings` | Pro | options | `array()` | None | `class-revenue-dashboard.php:1077` | `class-revenue-dashboard.php:123` | Settings blob | Revenue dashboard filter state. No sanitizer. |
| `wbam_page_advertiser_dashboard` | Pro | options | `0` | None | `class-pro-admin.php:4415, 4451` | `functions.php:28`, `get_advertiser_dashboard_url()` 5+ places | Page ID | UNSANITIZED. No register_setting. Direct update_option. |
| `wbam_page_classifieds` | Pro | options | `0` | None | `class-pro-admin.php:4415` | `functions.php:55`, `get_classifieds_url()` 3+ places | Page ID | UNSANITIZED. No register_setting. Direct update_option. |
| `wbam_page_my_classifieds` | Pro | options | `0` | None | `class-pro-admin.php:4415` | `functions.php:87` | Page ID | UNSANITIZED. No register_setting. Direct update_option. |
| `wbam_page_my_favorites` | Pro | options | `0` | None | `class-pro-admin.php:4415` | `functions.php:131` | Page ID | UNSANITIZED. No register_setting. Direct update_option. |
| `wbam_page_my_following` | Pro | options | `0` | None | `class-pro-admin.php:4415` | `functions.php:153` | Page ID | UNSANITIZED. No register_setting. Direct update_option. |
| `wbam_currency` | Free | options | `'USD'` | None | `class-partnership.php:203` | Free settings API | Settings | Free-only. Pro reads from `wbam_pro_settings['currency']`. |
| `wbam_link_prefix` | Free | options | `''` | None | `class-link-cloaker.php:71` | `class-link-cloaker.php:68` | Settings | Free-only direct option. Pro links module reads Free's `wbam_settings['link_cloak_prefix']`. DUPLICATE-KEY. |
| `wbam_setup_complete` | Free | options | `false` | None | `class-setup-wizard.php:586` | `class-setup-wizard.php:62` | Flag | Free wizard completion flag. Pro has own `wbam_pro_setup_complete`. FREE-PRO-OVERLAP. |
| `wbam_setup_dismissed` | Free | options | `false` | None | `class-setup-wizard.php:108` | `class-setup-wizard.php:62` | Flag | Free wizard dismiss flag. FREE-PRO-OVERLAP. |
| `wbam_demo_data_ids` | Free | options | `array()` | None | `class-setup-wizard.php:578`, `class-installer.php:253` | `class-setup-wizard.php:565` | Registry | Demo data ID tracking. Used by both Free and Pro. FREE-PRO-OVERLAP. |
| `wbam_demo_data_backfilled_v2` | Free | options | `0` | None | `class-installer.php:256` | `class-installer.php:179` | Flag | DB 2.2.0 migration marker. |
| `wbam_email_submissions` | Free | options | `array()` | None | `class-frontend.php:415` | `class-frontend.php:401` | Array | Email form submissions. Written directly via update_option. UNSANITIZED. FREE-PRO-OVERLAP. |
| `wbam_format_matching_enabled` | Free/Pro | options | `false` | None | `class-next-step-banner.php:138` (Pro) | Read in `class-placement-guard.php:88` (Pro), `class-frequency-cap-pro.php:88` (Pro) | Flag | DEAD-READ in Pro: Written by Free's banner, read by Pro's placement guard, but only if Free is active. If Free disabled, setting orphans. |
| `wbam_pro_setup_complete` | Pro | options | `false` | None | `class-pro-plugin.php:180-181` | `class-pro-admin.php:92`, others | Flag | Pro setup wizard completion. |
| `wbam_pro_needs_setup` | Pro | options | `true` | None | `wb-ad-manager-pro.php:181` | `class-setup-wizard.php:92` | Flag | Pro wizard entry gate. |
| `wbam_pro_db_version` | Pro | options | `DB_VERSION` | None | `class-installer.php:2419` | `class-installer.php:67, 703` | Version | DB version marker for migrations. |
| `wbam_pro_license_key` | Pro | options | `''` | None | `class-wbam-pro-license-manager.php:447, 481, 612` | `class-wbam-pro-license-manager.php:110, 119, 550` | Secret | License key. UNSANITIZED. No trim() before store. |
| `wbam_pro_license_status` | Pro | options | `''` | None | `class-wbam-pro-license-manager.php:613, 694` | `class-wbam-pro-license-manager.php:120` | Status | License validity. |
| `wbam_pro_license_data` | Pro | options | `null` | None | `class-wbam-pro-license-manager.php:614, 695` | `class-wbam-pro-license-manager.php:121` | Object | Serialized license data. |
| `wbam_pro_demo_data_ids` | Pro | options | `array()` | None | `class-installer.php:566` | `template:admin/setup-wizard.php:234` | Registry | Pro demo data registry. Separate from Free's `wbam_demo_data_ids`. HARDCODED-DEFAULT appears in 2 places. |
| `wbam_pro_demo_migration_360_done` | Pro | options | `0` | None | `class-installer.php:567` | `class-installer.php:377` | Flag | DB 3.6.0 migration marker. Ensures demo backfill runs once. |
| `wbam_pro_legacy_demo_pages_exist` | Pro | options | `0` | None | `class-pro-admin.php:3027` (delete), `class-installer.php:570` (set) | `class-pro-admin.php:3051` (get) | Flag | DEAD-WRITE: Set at migration, checked once, never acted upon. Orphaned. |
| `wbam_ab_testing_db_version` | Pro | options | `'1.0'` | None | `class-installer.php:828` | Never read (grepped: 0 results) | Version | DEAD-WRITE: Set for AB testing module, never checked. No migration needed (module stable). |
| `wbam_last_health_check` | Pro | options | `''` | None | `class-links-pro-module.php:1066` | `class-links-pro-module.php:695` | Timestamp | Links module health check timestamp. UNSANITIZED write (no sanitize on timestamp string). |
| `wbam_credits_purchase_url` | Pro | options | `''` | None | `class-credits-settings.php:633` | `class-credits-bridge.php:629` | URL | Credits module purchase redirect. UNSANITIZED. Admin only but no nonce check on POST. |
| `wbam_pro_rest_legacy_routes` | Pro | options | `true` | None | Never written | `class-classified-api.php:76` | Flag | DEAD-READ: Default `true`, never written by Pro code. Orphaned boolean check. |
| `wbam_bump_price` | Free | options | `1` | None | Never written to directly in Free | `class-classified-shortcodes.php:1167` (Free read via get_option), but written by Pro as part of classifieds form | Price | DEAD-WRITE from Free perspective. Written to `wbam_pro_classifieds_settings['bump_price']` by Pro admin, but Free code still reads from this option. DUPLICATE-KEY. |
| **Post Meta** | | | | | | | | |
| `_wbam_ad_enabled` | Free/Pro | postmeta | `'0'` | None | 20+ places in both | 15+ places in both | Ad status | Ad enable/disable flag per post. Demo data uses '1', submission uses '0'. HARDCODED-DEFAULT in 5 files. |
| `_wbam_ad_data` | Free/Pro | postmeta | `array()` | None | Setup wizard, API | Placement engines, ad types, API | Ad data | Serialized ad content. Read by Free placement engine, written by both. |
| `_wbam_placements` | Free/Pro | postmeta | `array()` | None | API, setup wizard | Placement engine, API | Placements | Serialized array of placement IDs assigned to ad. |
| `_wbam_priority` | Free/Pro | postmeta | `5` or `0` | None | Setup wizard, API | Frequency manager, sort | Priority | Ad display priority/weight. Setup uses 5, API uses absint(). HARDCODED-DEFAULT in 2 places. |
| `_wbam_is_responsive` | Free/Pro | postmeta | `'0'` | None | Installer backfill, API | Ad format resolver, placement engine | Responsive flag | Marks ad as responsive. Backfilled by installer migration. |
| `_wbam_ad_format` | Free/Pro | postmeta | Computed | None | Installer, not directly set | Ad format resolver, API | Format | Ad format slug (e.g., '728x90', 'responsive'). Populated by installer backfill. |
| `_wbam_ad_width` | Free/Pro | postmeta | `int` from data | None | Demo setup, installer backfill | Ad format resolver | Dimension | Ad width in pixels. |
| `_wbam_ad_height` | Free/Pro | postmeta | `int` from data | None | Demo setup, installer backfill | Ad format resolver | Dimension | Ad height in pixels. |
| `_wbam_geo_targeting` | Free | postmeta | `array()` | None | Geo engine (API) | Geo engine read | Rules | Geo targeting rules per ad (Free only). |
| `_wbam_is_demo` | Free/Pro | postmeta | `0` or `1` | None | Demo setup, installer backfill | Demo cleaner, multiple queries | Flag | Marks demo content for cleanup. Backfilled post-activation. |
| **Pro-Only Post Meta** | | | | | | | | |
| `_wbam_ad_type` | Pro | postmeta | `'image'` | None | Demo setup | Ad type resolver | Type | Ad type (image, code, adsense, etc.). Part of legacy ad schema. |
| `_wbam_ad_image_url` | Pro | postmeta | URL | None | Demo setup | Ad type display | URL | Image ad source URL. Deprecated in favor of `_wbam_ad_data`. |
| `_wbam_ad_link` | Pro | postmeta | URL | None | Demo setup | Ad shortcode rendering | URL | Ad click-through URL. Legacy key. |
| `_wbam_ad_image_id` | Pro | postmeta | `int` | None | Demo setup, classifier | Image selection | ID | Attachment ID. Used by image ad classifier. |
| `_wbam_ad_new_window` | Pro | postmeta | `'1'` or `'0'` | None | Demo setup | Ad rendering templates | Flag | Open ad in new window. Legacy. |
| `_wbam_ad_nofollow` | Pro | postmeta | `'1'` or `'0'` | None | Demo setup | Ad rendering | Flag | Add rel=nofollow. Legacy. |
| `_wbam_ad_placement` | Pro | postmeta | placement ID | None | Demo setup | Ad query filtering | ID | Original placement assignment (legacy). |
| `_wbam_ad_weight` | Pro | postmeta | `50` | None | Demo setup | Rotation module | Weight | Ad weight for rotation (deprecated, use priority). |
| `_wbam_ad_size` | Pro | postmeta | `'300x250'` | None | Demo setup | Display templates | Size | Ad size string. Legacy. |
| `_wbam_advertiser_id` | Pro | postmeta | `int` | None | Demo setup, submission handler | Advertiser queries | ID | Ad owner advertiser ID. Links ad to advertiser user. Used by 5 places. |
| `_wbam_campaign_id` | Pro | postmeta | `int` | None | Demo setup, submission handler | Campaign manager | ID | Campaign assignment. Used by 2 places. |
| `_wbam_code_sandbox` | Free | postmeta | `false` | None | API write | Code ad type | Flag | Sandbox code ad embeds. UNSANITIZED update. |
| `_wbam_gallery_ids` | Pro | postmeta | `array()` | None | Demo setup (classifieds) | Classified display | Array | Classified gallery image IDs. |
| `_wbam_classified_price` | Pro | postmeta | `float` | None | Demo setup | Classified display | Price | Classified item price. Demo-only. |
| `_wbam_classified_category` | Pro | postmeta | `string` | None | Demo setup | Classified display | Category | Classified category. Demo-only. |
| `_wbam_classified_condition` | Pro | postmeta | `string` | None | Demo setup | Classified display | Condition | Item condition (new/used). Demo-only. |
| `_wbam_classified_location` | Pro | postmeta | `string` | None | Demo setup | Classified display | Location | Classified location. Demo-only. |
| `_wbam_link_url` | Pro | postmeta | URL | None | Demo setup | Link display | URL | Cloaked link target URL. |
| `_wbam_link_slug` | Pro | postmeta | `string` | None | Demo setup | Link router | Slug | Link short code. |
| `_wbam_link_group` | Pro | postmeta | `string` | None | Demo setup | Link filtering | Group | Link category. |
| `_wbam_link_nofollow` | Pro | postmeta | `'1'` or `'0'` | None | Demo setup | Link rendering | Flag | Add rel=nofollow. |
| `_wbam_link_new_window` | Pro | postmeta | `'1'` | None | Demo setup | Link rendering | Flag | Open in new tab. |
| `_wbam_link_enabled` | Pro | postmeta | `'1'` | None | Demo setup, API | Link filter queries | Flag | Link enable/disable. |
| `_wbam_link_clicks` | Pro | postmeta | `0..N` | None | Demo setup | Link analytics | Count | Click count. Updated by link tracker. |
| `_wbam_links_scanned` | Pro | postmeta | timestamp | None | Link scanner | Link scanner | Timestamp | Last post scan time. |
| `_wbam_links_count` | Pro | postmeta | `int` | None | Link scanner | Link scanner | Count | Detected internal links in post. |
| `_wbam_submission_date` | Pro | postmeta | timestamp | None | Demo setup, ad submission form | Email display | Timestamp | Ad submission timestamp. Demo-only. |
| **User Meta** | | | | | | | | |
| `_wbam_advertiser_id` | Pro | usermeta | `int` | None | Never written | Pro user profile integration (expected but not found) | ID | Advertiser profile link (expected on advertiser users, not found in current code). |
| `wp_wbam-admin-pointer-dismissed` (POINTER_META) | Free | usermeta | `array()` | None | `class-first-install-pointers.php:187` | `class-first-install-pointers.php:100` | Pointers | WP admin pointers dismissal state. Suffixed by user ID. |

---

## Issue Details

### UNSANITIZED (12 keys)

These options are updated via `update_option()` without a sanitizer callback registered:

1. **`wbam_pro_settings`** — HAS sanitizer, but only at register_setting level. Tabs that bypass Settings API can call update_option directly.
2. **`wbam_pro_classifieds_settings`** — NO sanitizer. Direct update_option in `class-pro-admin.php:3663`.
3. **`wbam_pro_email_settings`** — NO sanitizer. Direct update_option in `class-pro-admin.php:4283`.
4. **`wbam_pro_geolocation_settings`** — NO sanitizer. Direct update_option in `class-pro-admin.php:4212`.
5. **`wbam_pro_revenue_settings`** — NO sanitizer. Direct update_option in `class-revenue-dashboard.php:1077`.
6. **`wbam_page_*` (5 keys)** — NO sanitizer. All `wbam_page_{key}` options use direct update_option without register_setting. Examples: `wbam_page_advertiser_dashboard`, `wbam_page_classifieds`, etc.
7. **`wbam_last_health_check`** — NO sanitizer. Written as timestamp string. Weak validation.
8. **`wbam_credits_purchase_url`** — Uses `esc_url_raw()` on write but no sanitizer callback. Admin-only but risky.
9. **`wbam_pro_license_key`** — NO sanitizer. trimmed() but not sanitized. Stored in DB directly.
10. **`_wbam_code_sandbox`** (postmeta) — NO sanitizer. Updated via API without validation.
11. **`wbam_email_submissions`** — NO sanitizer. Written directly by frontend form handler.
12. **`wbam_settings[link_cloak_prefix]`** — Registered via Free's Settings API, but Free Plugin code also writes directly: `class-link-cloaker.php:71`.

**Recommendation:** Either register all options with sanitizers, or migrate to using only Settings API where possible.

---

### DEAD-READ (3 keys)

These are read by code but never written by the plugin:

1. **`wbam_format_matching_enabled`**
   - Written by: Free plugin's `class-next-step-banner.php:138`
   - Read by: Pro's `class-placement-guard.php:88` and `class-frequency-cap-pro.php:88`
   - **Issue:** If Free plugin is disabled/uninstalled, setting is orphaned. Pro has no fallback to set it.
   - **Impact:** Format matching feature silently disabled if Free deactivated.

2. **`wbam_pro_rest_legacy_routes`**
   - Written by: Never
   - Read by: `class-classified-api.php:76` (checks if legacy routes enabled)
   - **Issue:** Defaults to `true` in code. No way to change it without direct DB access.
   - **Impact:** Dead code if feature was meant to be toggleable.

3. **`wbam_bump_price`**
   - Written by: Free's classifieds form (not found in grep)
   - Read by: Free's `class-classified-shortcodes.php:1167`
   - **Issue:** Neither plugin writes to this option directly. Falls back to hardcoded default `1`.
   - **Impact:** Setting assumed but not maintained. Bug if Free expected to write it.

---

### DEAD-WRITE (2 keys)

These are written but never read:

1. **`wbam_pro_legacy_demo_pages_exist`**
   - Written by: `class-installer.php:570`
   - Read by: `class-pro-admin.php:3051` (get only, no logic)
   - **Issue:** Marker for demo page migration, but no action taken on read.
   - **Impact:** Orphaned DB clutter.

2. **`wbam_ab_testing_db_version`**
   - Written by: `class-installer.php:828` (set to `'1.0'`)
   - Read by: Never (grepped 0 results)
   - **Issue:** No migration logic checks this version.
   - **Impact:** Wasted write. Can be removed.

---

### DUPLICATE-KEY (2 pairs)

1. **Currency setting:**
   - Free writes to: `wbam_settings[currency]`
   - Pro reads from: `wbam_pro_settings[currency]`
   - **Issue:** Two sources of truth. Free plugin can set currency, Pro ignores it.
   - **Impact:** Multi-source confusion. No sync between plugins.

2. **Link cloak prefix:**
   - Free writes to: `wbam_settings[link_cloak_prefix]` (via Settings API register_setting)
   - Also written to: `wbam_link_prefix` (direct update_option in `class-link-cloaker.php:71`)
   - **Issue:** Two keys for same setting.
   - **Impact:** Inconsistent reads. Pro's Keyword_Linker reads Free's `wbam_settings['link_cloak_prefix']`, but Free's own Link_Cloaker writes `wbam_link_prefix`.

---

### HARDCODED-DEFAULT (5 patterns)

These defaults are duplicated in 3+ files:

1. **`'USD'` (currency)**
   - Files: `Settings_Helper::get_currency()` (Pro), `class-payment.php` (Pro), `keyword-linker.php` (Pro, fallback)
   - **Action:** Move to `Settings_Helper::DEFAULT_CURRENCY` constant.

2. **`0` (page ID)**
   - Files: `Settings_Helper::get_page()` (Pro), `functions.php` (Pro, 5 places)
   - **Action:** Centralize in Settings_Helper.

3. **`'go'` (link prefix)**
   - Files: `class-link.php:278`, `class-link-cloaker.php:51`, `class-link-cloaker.php:175`
   - **Action:** Move to Settings_Helper.

4. **`false` (boolean flags)**
   - Files: Multiple `is_enabled()` reads with hardcoded `false` fallback.
   - **Action:** Use Settings_Helper defaults.

5. **`array()` (settings blobs)**
   - Files: 8+ places default empty array for module settings.
   - **Action:** Centralize with Installer initialization.

---

### FREE-PRO-OVERLAP (7 keys)

These settings exist in **both plugins** — cause migration debt:

| Key | Free | Pro | Conflict |
|-----|------|-----|----------|
| `wbam_settings` | Yes (main) | No direct read | Free-only. Pro uses `wbam_pro_settings`. If Free uninstalled, all Free settings lost. |
| `wbam_demo_data_ids` | Yes (written) | Yes (written) | Both write, single array. Could diverge during simultaneous activation. |
| `wbam_setup_complete` | Yes | No (has own) | Free: `wbam_setup_complete`. Pro: `wbam_pro_setup_complete`. Separate flags. |
| `wbam_setup_dismissed` | Yes | No (inherits) | Free flag. Pro reads it for wizard gate. If Free uninstalled, Pro wizard may misbehave. |
| `wbam_email_submissions` | Yes (form buffer) | No direct use | Free form writes, not used by Pro email system. Orphaned if Free disabled. |
| `wbam_currency` | Yes (Free writes) | No (reads from Pro settings) | Two sources. Free can set, Pro ignores. |
| `wbam_link_prefix` | Yes (Free reads/writes) | Pro reads (indirectly) | Free plugin handles, Pro piggybacks. If Free disabled, link module broken. |

**Impact:** Uninstalling Free plugin breaks several Pro features. No safe migration path.

---

## Methodology

### Search Coverage

**Patterns grepped:**
- `get_option(`, `update_option(`, `delete_option()` with string keys
- `Settings_Helper::(get|update|delete)` calls
- `register_setting(` calls
- `add_settings_field(`, `add_settings_section(` calls
- `get_post_meta(`, `update_post_meta(` with `_wbam_*` keys
- `get_user_meta(`, `update_user_meta(` with `_wbam_*` keys

**Scope:**
- Pro plugin: `/includes`, `/templates`, `/license`, `/demo-data-setup.php`
- Free plugin: `/includes`, `/templates`, `/admin`
- Excluded: `docs/`, `tests/`, `dist/`, build artifacts

**Files analyzed:** 180+ PHP files with settings-related code

### Exclusions

- WordPress core options (`siteurl`, `admin_email`, `date_format`, `time_format`)
- Transient keys (not permanent settings)
- Cache keys and temporary data
- REST API endpoint-specific data
- Session/user-specific runtime state
- Direct `$_POST`, `$_GET` access (only final persist calls)

---

## Recommendations

### Immediate (P0)

1. **Add sanitizers to all module-specific settings blobs:**
   - `wbam_pro_classifieds_settings` — validate payment prices, emails
   - `wbam_pro_email_settings` — validate email addresses, sender names
   - `wbam_pro_geolocation_settings` — validate provider selection
   - `wbam_pro_payment_settings` — validate gateway API keys

2. **Register all `wbam_page_*` options:**
   ```php
   register_setting( 'wbam_pages_group', 'wbam_page_advertiser_dashboard', 
     array( 'sanitize_callback' => 'absint' ) );
   ```

3. **Resolve FREE-PRO-OVERLAP:**
   - Pro: Stop reading Free's `wbam_settings[currency]` and `wbam_settings[link_prefix]`
   - Migrate Free settings into Pro's schema at activation
   - Add uninstall hook to migrate Free→Pro settings before deletion

### Short-term (P1)

4. **Remove DEAD-READ/DEAD-WRITE keys:**
   - Delete `wbam_pro_legacy_demo_pages_exist`
   - Delete `wbam_ab_testing_db_version`
   - Investigate `wbam_format_matching_enabled` — determine single owner

5. **Consolidate DUPLICATE-KEY settings:**
   - Currency: Use Pro's `wbam_pro_settings[currency]` as single source
   - Link prefix: Use Free's Settings API persistently (remove direct option write)

### Long-term (P2)

6. **Centralize HARDCODED-DEFAULT values:**
   ```php
   // In Settings_Helper
   const DEFAULT_CURRENCY = 'USD';
   const DEFAULT_PAGE_ID = 0;
   const DEFAULT_LINK_PREFIX = 'go';
   ```

7. **Add schema documentation:**
   - Create `docs/settings-schema.md` listing all keys, defaults, sanitizers
   - Add `@since` version for each key (when introduced)
   - Document migration paths for version upgrades

8. **Unit tests for Settings_Helper:**
   - Verify all keys have defaults
   - Verify all module blobs initialize with Installer
   - Test Free→Pro data migration

---

**Audit completed:** 2026-04-19  
**Auditor:** Claude Code Settings Inventory Tool  
**Next steps:** Implement P0 recommendations before marketing push.
