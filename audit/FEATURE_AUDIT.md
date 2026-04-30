# WB Ads Rotator with Split Test (FREE) — Feature Audit Report

**Generated**: 2026-04-30
**Version**: 2.8.0
**Source**: [`audit/manifest.json`](manifest.json) · [`audit/manifest.summary.json`](manifest.summary.json)
**Counts**: 21 REST routes (22 method bindings) · 7 AJAX handlers · 9 admin pages · 6 shortcodes · 1 CPT · 7 DB tables · 73 actions + 57 filters fired · 32 services
**Companion**: paired with `wb-ad-manager-pro` (PRO refactored Wallet to Credits SDK; this FREE plugin unchanged. FREE/PRO architecture contract authoritative at [`../wb-ad-manager-pro/plan/free-pro-architecture-contract.md`](../../wb-ad-manager-pro/plan/free-pro-architecture-contract.md))

---

## 1. Frontend features

### 1.1 Ad rendering (CPT `wbam-ad`)
- **CPT slug**: `wbam-ad` (private, `show_ui: true`, no archive, no REST)
- **Engine**: `WBAM\Modules\Placements\Placement_Engine` — singleton
- **Render entry**: `Placement_Engine::render_ad( $ad_id, $context )` returns HTML
- **Display gate**: `apply_filters('wbam_should_display_ad', true, $ad_id, $context)` — Pro hooks here for frequency cap + campaign pacing
- **Output filter**: `apply_filters('wbam_ad_output', $html, $ad_id, $context)` — Pro hooks here for impression tracking

### 1.2 Placements (13 types)
Each lives in `includes/Modules/Placements/class-*-placement.php`:
- after-archive, before-archive, comment, content, footer, header, paragraph, popup, shortcode, sticky, widget
- Plus `Placement_Engine` (manager) + `interface-placement.php`

### 1.3 Ad types (5 + interface)
`includes/Modules/AdTypes/`:
- `code` — raw HTML/JS (sandboxed iframe via `wbam_code_ad_use_sandbox` filter)
- `image` — image with alt + destination URL
- `email-capture` — inline opt-in form (writes to `wbam_email_submissions`)
- `rich-content` — WYSIWYG-rendered HTML
- `ad-sense` — Google AdSense slot (auto-enqueues `wbam-adsense` script)

### 1.4 BuddyPress integration (conditional)
- `BP_Activity_Placement` — render in activity stream
- `BP_Directory_Placement` — render in member/group directories
- `BP_Widgets` — sidebar ad widget
- Only loaded when `class_exists('BuddyPress')`

### 1.5 bbPress integration (conditional)
- `bbPress_Module` — adds placement points in forum threads
- Only loaded when `class_exists('bbPress')`

### 1.6 Jetonomy integration (conditional)
- `Jetonomy_Module` — placement support inside Jetonomy spaces
- Only loaded when `Jetonomy_Module::is_jetonomy_active()`

### 1.7 Links + Partnerships system
- **CPT-less** — uses 4 custom tables (`wbam_links`, `wbam_link_categories`, `wbam_link_clicks`, `wbam_link_partnerships`)
- Partnership inquiry form: `[wbam_partnership_inquiry]` shortcode → AJAX submit → `wbam_link_partnerships` table
- Link cloaking: `Link_Cloaker` intercepts `init` to handle pretty-link redirects
- Link click tracking: REST `/links/{id}/track` + AJAX `wbam_track_link_click` → `wbam_link_clicks` table

## 2. AJAX handlers (7)

| Action | nopriv | Handler | Nonce | Capability |
|---|---|---|---|---|
| `wbam_dismiss_notice` | no | `Plugin::ajax_dismiss_notice` | `wbam_dismiss_notice` | `is_admin` |
| `wbam_track_click` | yes | `Frontend::handle_click_tracking` | `wbam_frontend` | — |
| `wbam_email_capture` | yes | `Frontend::handle_email_capture` | `wbam_frontend` | — |
| `wbam_dismiss_setup` | no | `Setup_Wizard::dismiss_setup` | `wbam_setup_dismiss` | `manage_options` |
| `wbam_dismiss_pointer` | no | `First_Install_Pointers::ajax_dismiss` | `wbam_pointer` | `is_admin` |
| `wbam_track_link_click` | yes | `Links_Module::ajax_track_click` | `wbam_link_track` | — |
| `wbam_submit_partnership` | yes | `Partnership_Form::handle_ajax_submission` | `wbam_partnership_form` | — |

## 3. REST endpoints (21 routes, namespace `wbam/v1`)

See `manifest.json#/rest/endpoints` for full list. Grouped:

| Controller | Routes | Public | Admin |
|---|---|---|---|
| `Ads_API` | `/ads` (GET/POST), `/ads/serve`, `/ads/placements`, `/ads/types`, `/ads/track`, `/ads/{id}` (GET/PUT/DELETE), `/ads/{id}/stats`, `/ads/{id}/duplicate` | 5 | 6 |
| `Analytics_API` | `/analytics/overview`, `/analytics/ads/{id}`, `/analytics/daily`, `/analytics/track` | 1 | 3 |
| `Links_API` | `/links` (GET/POST), `/links/categories` (GET/POST), `/partnerships`, `/partnerships/{id}`, `/links/{id}` (GET/PUT/DELETE), `/links/{id}/stats`, `/links/{id}/track` | 1 | 10 |
| `Settings_API` | `/settings` (GET/PUT), `/settings/display` (GET/PUT) | 0 | 4 |

**Public endpoints are rate-limited** via per-IP transients (`wbam_rl_*`):
- `/ads/track`, `/analytics/track`: 60 events/min/IP
- `/links/{id}/track`: 30 events/min/IP

## 4. Admin pages (9)

| Title | Slug | Parent | Cap | Source |
|---|---|---|---|---|
| WB Ad Manager (Ads CPT) | `edit.php?post_type=wbam-ad` | top-level | `edit_posts` | `class-plugin.php:237` |
| Settings | `wbam-settings` | wbam-ad CPT | `manage_options` | `class-settings.php:76` |
| Setup Wizard | `wbam-setup` | `index.php` | `manage_options` | `class-setup-wizard.php` |
| Help & Documentation | `wbam-help-docs` | wbam-ad CPT | `manage_options` | `class-help-docs.php:59` |
| Upgrade to Pro | `wbam-upgrade-pro` | wbam-ad CPT | `manage_options` | `class-upgrade-pro.php:58` (hidden when Pro active) |
| Links | `wbam-links` | top-level | `manage_options` | `class-links-admin.php:55` |
| Categories | `wbam-link-categories` | wbam-links | `manage_options` | `class-links-admin.php:66` |
| Add New Link | `wbam-link-edit` | wbam-links | `manage_options` | `class-links-admin.php:76` |
| Partnerships | `wbam-partnerships` | wbam-links | `manage_options` | `class-partnership-admin.php:56` |

## 5. Settings inventory

Master option: **`wbam_settings`** (single array; access via `WBAM\Core\Settings_Helper::get()`).

| Sub-key | Type | Default | Controls |
|---|---|---|---|
| `disable_frontend_css` | bool | false | Skip enqueueing `wbam-frontend.css` |
| `lazy_load_ads` | bool | false | Defer ad rendering with IntersectionObserver |
| `ad_label` | bool | true | Show "Advertisement" label above ads |
| `ad_label_text` | string | "Advertisement" | The label text |
| `ad_container_class` | string | "" | Extra class on ad wrapper |
| `responsive_ads` | bool | true | Generate responsive-image markup |
| `hide_on_mobile` | bool | false | Suppress ads on viewports ≤640px |
| `hide_on_tablet` | bool | false | Suppress ads on viewports ≤1024px |

Ancillary options:
- `wbam_setup_complete` (bool) — wizard completion flag
- `wbam_setup_dismissed` (bool) — wizard dismissed without completing
- `wbam_db_version` (string) — DB schema version for `Installer::needs_update()`
- `_wbam_activation_redirect` (transient) — one-shot activation redirect

## 6. Database tables (7)

All prefixed `{wp_prefix}wbam_`:

| Table | Purpose |
|---|---|
| `wbam_links` | Tracked links/affiliate URLs (id, name, destination_url, slug, link_type, status, category_id, click_count, nofollow, sponsored, new_tab, cloaking_enabled, payment fields) |
| `wbam_link_categories` | Link category taxonomy (id, name, slug, description, link_count) |
| `wbam_link_clicks` | Per-click event log (link_id, visitor_hash, referrer, clicked_at) |
| `wbam_analytics` | Ad event log (ad_id, event_type [impression/click], placement, created_at) |
| `wbam_email_submissions` | Email capture form submissions |
| `wbam_link_partnerships` | Partnership inquiry submissions (name, email, website_url, partnership_type, anchor_text, message, status, admin_notes) |
| `wbam_rate_limits` | Per-IP rate limit counters (alternative to transient store) |

Source: `includes/Core/class-installer.php` (lines 357, 395, 412, 425, 455, 472, 499)

## 7. Custom Post Types (1)

**`wbam-ad`** — internal ad records:
- `public: false`, `publicly_queryable: false`, `exclude_from_search: true`
- `show_ui: true`, `show_in_menu: true`, `menu_position: 25`, `menu_icon: dashicons-megaphone`
- `supports: ['title']`, `has_archive: false`, `show_in_rest: false`
- Source: `includes/Core/class-plugin.php:237`

## 8. JavaScript modules

| Handle | Source | Deps | Context | Purpose |
|---|---|---|---|---|
| `wbam-frontend` | `assets/js/frontend.js` | jquery | frontend | Click/impression tracking, lazy load |
| `wbam-adsense` | googlesyndication CDN | — | frontend | AdSense loader |
| `wbam-lucide` | lucide CDN | — | both | Icon font (auto-registered) |
| `wbam-links-tracking` | `assets/js/links-tracking.js` | — | frontend | Link click outbound tracking |
| `wbam-partnership-form` | `assets/js/partnership-form.js` | jquery | frontend | Form submit + validation |
| `wbam-admin` | `assets/js/admin.js` | jquery | admin | Admin UI behaviors |

## 9. CSS modules

| Handle | Source | Deps | Context |
|---|---|---|---|
| `wbam-frontend` | `assets/css/frontend.css` | — | frontend |
| `wbam-admin` | `assets/css/admin.css` | — | admin |
| `wbam-setup` | `assets/css/setup-wizard.css` | — | admin |
| `wbam-help-docs` | `assets/css/help-docs.css` | — | admin |
| `wbam-upgrade-pro` | `assets/css/upgrade-pro.css` | — | admin |
| `wbam-field-tooltips` | `assets/css/field-tooltips.css` | dashicons | admin |
| `wbam-list-empty-states` | `assets/css/list-empty-states.css` | dashicons | admin |
| `wbam-links-frontend` | `assets/css/links-frontend.css` | — | frontend |
| `wbam-links-admin` | `assets/css/links-admin.css` | — | admin |
| `wbam-partnerships-admin` | `assets/css/partnerships-admin.css` | — | admin |

## 10. Email templates

_None_ — Email handling is fired through `Partnership_Emails::send_*` methods which use `wp_mail()` with HTML built inline (filters: `wbam_partnership_email_headers`, `wbam_partnership_admin_notification_message`, etc.). No template files in `templates/`.

## 11. Cron jobs

_None_ — FREE plugin uses no scheduled events. (Pro adds analytics aggregation crons.)

## 12. Integrations

| Integration | Detection | Module |
|---|---|---|
| BuddyPress | `class_exists('BuddyPress')` | `WBAM\Modules\BuddyPress\BP_Module` |
| bbPress | `class_exists('bbPress')` | `WBAM\Modules\bbPress\bbPress_Module` |
| Jetonomy | `Jetonomy_Module::is_jetonomy_active()` | `WBAM\Modules\Jetonomy\Jetonomy_Module` |
| Google AdSense | `wbam-adsense` ad type registered | `WBAM\Modules\AdTypes\AdSense_Ad` |
| WP Abilities API (6.9+) | `function_exists('wp_register_ability')` | `WBAM\Core\Abilities` |

## 13. Custom capabilities

_None_ — uses WP core `manage_options` exclusively for admin and `edit_posts` for the CPT.

## 14. Pro extension points (KEY hooks Pro depends on)

| Hook | Type | Purpose |
|---|---|---|
| `wbam_init` | action | Plugin init complete (Pro bootstraps after this) |
| `wbam_should_display_ad` | filter | Final gate before render — Pro: frequency cap, campaign pacing |
| `wbam_ad_output` | filter | Filter rendered ad HTML — Pro: impression tracking wrapper |
| `wbam_currency_symbol` | filter | Pro overrides for multi-currency |
| `wbam_setup_wizard_sample_save_after` | action | Pro saves advertiser-flag option here |
| `wbam_save_ad_meta` | action | Pro syncs `_wbam_ad_type` metakey for type analytics |
| `wbam_ad_metabox_options` | action | Pro adds A/B test, scheduling, campaign fields to ad edit |
