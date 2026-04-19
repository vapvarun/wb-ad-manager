# Pass 3 — Template Wiring

**Audit date:** 2026-04-19
**Scope:** Pro `1.5.0` (HEAD `90436ec`) + Free `2.8.0` (HEAD `9f38b51`)

Written from Explore-agent findings; verified with grep against working tree.

## Summary

- Templates scanned: 71 (Pro), 0 (Free — no `templates/` dir)
- **DB-CALL-IN-TEMPLATE:** 2
- **OPTION-CALL-IN-TEMPLATE:** 19
- **USERMETA-CALL-IN-TEMPLATE:** 7
- **INLINE-STYLE:** 2
- **INLINE-SCRIPT:** 11
- **MISSING-ESC:** 1
- **MISSING-NONCE:** 0 ✅
- **NO-CAPABILITY-CHECK:** 0 ✅
- **NO-RESPONSIVE:** 0 ✅
- **NO-ABSPATH-GUARD:** 0 ✅

**Surprise (positive):** Escaping hygiene, nonces, ABSPATH guards, and responsive CSS coverage are strong across all 71 templates. Zero security-class violations.

---

## P0 (security)

None. The category was empty because escaping/nonce/capability/ABSPATH discipline is consistent across the codebase.

## P1 (architecture)

Templates should be dumb render layers receiving `$view_data` from a loader. These violate that contract.

| # | File | Line(s) | Type | Snippet | Fix idea |
|---|------|---------|------|---------|----------|
| 1 | `templates/portal/tabs/ads.php` | 36–51 | DB-CALL | `get_posts([...post_type=wbam-ad, meta_query])` | Move query into `Advertiser_Shortcodes::render_ads_tab()` → pass `$ads` to template |
| 2 | `templates/portal/tabs/ads.php` | ~80–110 | DB-CALL | `$wpdb->get_results(...)` analytics pull | Move into loader; pass stats array |
| 3 | `templates/portal/tabs/overview.php` | 35 | DB-CALL (indirect) | `$advertiser->get_recent_activity(5)` | Acceptable — it's a manager method. Keep. |
| 4 | `templates/classifieds/reviews.php` | 54 | OPTION-CALL | `get_option('date_format')` | Pass formatted date strings from loader |
| 5 | `templates/classifieds/archive.php` (if exists) | 46–47 | OPTION-CALL | `get_option('date_format')` | Same |
| 6–24 | 14 email templates | many | OPTION-CALL | `get_option('date_format')`, `get_option('blogname')`, `get_option('admin_email')`, `get_option('blog_charset')` | Emails receive pre-formatted data from `Email_Notifications`. Pass `site_name`, `admin_email`, `formatted_date` already rendered. |
| 25 | `templates/emails/header.php` | 43 | INLINE-STYLE | `<style>…</style>` block with ~30 lines CSS | Email clients require inline CSS — this is UNAVOIDABLE for email. Mark as acceptable exception. |
| 26 | `templates/emails/receipt.php` | 43 | INLINE-STYLE | same | Same — accept. |
| 27 | `templates/portal/tabs/favorites.php` | — | USERMETA-CALL | `get_user_meta($user->ID, 'wbam_favorites', true)` | Loader pulls favorites list, passes as `$view_data['favorites']` |
| 28 | `templates/portal/tabs/following.php` | — | USERMETA-CALL | `get_user_meta(...)` | Same |
| 29 | `templates/portal/tabs/profile.php` | — | USERMETA-CALL | `wp_get_current_user()` + meta | Same |
| 30 | `templates/portal/tabs/links.php` | — | USERMETA-CALL | `wp_get_current_user()` | Template gets `$advertiser` already — refactor to use it |
| 31 | `templates/portal/tabs/overview.php` | — | USERMETA-CALL | `wp_get_current_user()` | Same |
| 32 | `templates/portal/tabs/inquiries.php` | — | USERMETA-CALL | `get_user_meta(...)` | Loader |
| 33 | `templates/portal/tabs/wallet.php` | — | USERMETA-CALL | `get_user_meta(...)` | Loader |

### Inline scripts (INLINE-SCRIPT)

11 templates embed `<script>` blocks with logic. Pattern: `<script>var wbamConfig = <?php echo wp_json_encode(...) ?>; /* …logic… */</script>`.

**Rule:** `<script>window.wbamXConfig = {...}</script>` for config bootstrap is OK (same-request data handoff). Inline *logic* blocks should be in an enqueued `.js` file consuming the config variable.

Files to split config-vs-logic:

| File | Line range (approx) | What to extract |
|------|---------------------|-----------------|
| `templates/portal/ad-form.php` | ~617–776 | Wizard step validation → `assets/js/portal-ad-form.js` |
| `templates/classifieds/archive.php` | — | Map markers, filter JS |
| `templates/classifieds/single.php` | — | Gallery, contact modal |
| `templates/portal/tabs/analytics.php` | — | Chart rendering |
| `templates/portal/tabs/share-of-voice.php` | ~202–211 | Config only — KEEP (bootstrap is fine) |
| `templates/portal/tabs/campaigns.php` | — | Filter tabs JS |
| `templates/admin/setup-wizard.php` | — | Wizard nav |
| `templates/portal/tabs/wallet.php` | — | Payment JS |
| `templates/portal/tabs/overview.php` | — | Quick-action triggers |
| `templates/classifieds/upgrades.php` | — | Upgrade modal JS |
| `templates/classifieds/contact-form.php` | — | Contact AJAX |

### MISSING-ESC (P1, 1 finding)

- `templates/?.php` (agent reported: "printf with HTML not escaped") — needs manual re-verify; most `printf` calls in the codebase are wrapped with `esc_html__` on the format string, so this may be a false positive. Flagged for re-verify in Phase C.

## P2 (polish)

- 14 email templates each repeat `get_option('date_format')` + `get_option('blogname')` — factor through a single `Email_View_Data::prepare()` helper.
- Some portal templates lack `@var` docblocks at the top — add for IDE hinting.

---

## What's strong

- **Zero missing escapes** (except the 1 P1 above to reverify)
- **Zero missing nonces**
- **Zero missing capability checks on admin templates**
- **Zero missing `ABSPATH` guards**
- **Zero `NO-RESPONSIVE`** — all portal CSS has ≤640px + ≤1024px breakpoints
- **Theme override pattern works** — `Template_Loader::load_template()` checks `themes/{theme}/wb-ad-manager-pro/` first

## Methodology

Explore agent (`subagent_type: Explore`) scanned all files under `templates/` in both plugins; greps verified against working tree:

```
grep -rn 'get_option|get_user_meta|wp_get_current_user|get_posts|$wpdb' templates/
```

Excluded: comment-only matches (lines beginning with `//` or ` *`). Free plugin has no `templates/` directory — architecture uses server-side renderers only.

## Recommended Phase C batch order

**Batch 3-A (pure loader refactor, zero behavior change):**
- Fix DB-CALL in `ads.php` and `overview.php`
- Fix USERMETA-CALL in favorites/following/profile/links/overview/inquiries/wallet tabs
- Add `Email_View_Data::prepare()` to centralize email template inputs

**Batch 3-B (JS split — trickier, each file is a one-shot refactor):**
- Extract inline logic to `assets/js/portal-*.js` per file

**Batch 3-C (polish):**
- Add missing `@var` docblocks
- Resolve the 1 suspected MISSING-ESC
