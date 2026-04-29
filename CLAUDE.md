# WB Ads Rotator with Split Test - Claude Code Instructions

> **READ FIRST:** [`audit/manifest.json`](audit/manifest.json) is the canonical inventory — 22 REST routes, 7 AJAX handlers, 9 admin pages, 6 shortcodes, 7 DB tables, 1 CPT (`wbam-ad`), 73 actions + 57 filters fired, 32 services. Use this before grepping. See also [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md), [`audit/CODE_FLOWS.md`](audit/CODE_FLOWS.md), [`audit/ROLE_MATRIX.md`](audit/ROLE_MATRIX.md). Open `audit/graph.html` (via `cd audit && python3 -m http.server 8765`) for an interactive Cytoscape view. Refresh with `/wp-plugin-onboard --refresh` after non-trivial changes.

> **ARCHITECTURE CONTRACT** (lives in PRO repo since PRO consumes most of it): `../wb-ad-manager-pro/plan/free-pro-architecture-contract.md` is authoritative. Run `bash ../wb-ad-manager-pro/bin/architecture-checks.sh` before every commit — it enforces Free/Pro coupling rules including ones that affect this (FREE) plugin. Sprint plan for current cleanup: [`plan/2.9.0-pro-coupling-cleanup.md`](plan/2.9.0-pro-coupling-cleanup.md).

## Plugin Overview
**Plugin Name:** WB Ads Rotator with Split Test (FREE)
**Slug:** `wb-ads-rotator-with-split-test`
**Main File:** `wb-ads-rotator-with-split-test.php`
**Current Stable:** `2.8.0` — **Dev Branch:** `2.8.1`
**Type:** WordPress Plugin (Free — paired with `wb-ad-manager-pro`)

Ad rotation + A/B split-test plugin. Renamed from the legacy `buddypress-ads-rotator` plugin. **The old directory layout (`admin/`, `public/`, `buddypress-ads-rotator.php`) no longer exists** — everything is now under `includes/` with PSR-like class organization.

---

## First-clone setup (run once)

```bash
# 1. PHP dependencies
composer install

# 2. Activate the tracked git pre-push hook. This makes `git push` block on a
#    failing local-CI gate (composer verify-no-test). One-time per clone —
#    no symlinks or hook copying, just `git config core.hooksPath`.
composer install-hooks

# 3. (Optional) WP test framework for PHPUnit. Per-developer setup because
#    Local-by-Flywheel uses random per-site MySQL ports — you'll need yours.
#    bash bin/install-wp-tests.sh wbam_test root root <your-mysql-host:port> latest
#    Once installed: export WBAM_FULL_VERIFY=1 to run PHPUnit in the gate.
```

Day-to-day commands:

| Command | What it runs | When |
|---|---|---|
| `composer verify-no-test` | lint + phpstan + arch-checks + plugincheck + verify-flow | Default pre-push gate (no PHPUnit needed) |
| `composer verify` | the above + PHPUnit | When WP test scaffold is set up |
| `composer arch-checks` | Free/Pro contract enforcement only | Quick sanity check during development |
| `composer lint-fix` | phpcbf auto-fixes | Before lint to clear easy issues |

Push gates:
- Default: hook runs `composer verify-no-test` automatically before every push.
- Opt into full gate: `WBAM_FULL_VERIFY=1 git push`
- Skip gate (emergencies only): `WBAM_SKIP_VERIFY=1 git push`
- Bypass all hooks: `git push --no-verify` (don't make this a habit)

---

## Basecamp Project Management

> **⚠️ CURRENT BOARD:** `WB Ad Manager` (project `44982066`). Both Free and Pro plugins share this single board — cards are tagged `[Free]` or `[Pro]` in the title.
>
> **DO NOT USE** the legacy `BuddyPress Ads` board (`37595322`). It references the pre-rename plugin structure (`buddypress-ads-rotator.php`, `admin/`, `public/`) which no longer exists. Any `Ready for Development` / bug cards there are stale — the code paths they cite do not exist in this codebase. Ignore that board entirely.

### Project Details
- **Project Name:** WB Ad Manager
- **Project ID:** `44982066`
- **Project URL:** https://3.basecamp.com/5798509/buckets/44982066
- **Search term that resolves here:** `ad manager` (NOT `ads`, NOT `rotator`, NOT `split test` — those return nothing or the legacy board)

### Card Table Columns (all IDs)
| Column | ID |
|--------|-----|
| Triage | 9334950390 |
| Not now | 9334950392 |
| Scope | 9334950393 |
| Suggestions | 9334950395 |
| Bugs | 9334953095 |
| Ready for Development | 9334953466 |
| In Development | 9334953922 |
| Ready for Testing | 9334954442 |
| In Testing | 9334954687 |
| Done | 9334950396 |

### Workflow
1. Pick bugs from **Bugs** column — filter by `[Free]` prefix for this plugin
2. Validate fix locally + Playwright screenshot
3. Add comment with fix details to card
4. Push changes to git (`2.8.1` dev branch)
5. Move card to **Ready for Testing**

---

## Key Directories

```
wb-ads-rotator-with-split-test/
├── wb-ads-rotator-with-split-test.php   # Main plugin file
├── includes/
│   ├── Admin/           # Admin UI, settings, demo-data cleaner
│   ├── API/             # REST controllers (Ads, Analytics, Links, Settings)
│   ├── Core/            # Plugin bootstrap, abilities
│   ├── Frontend/        # Frontend rendering + analytics
│   └── Modules/
│       ├── AdTypes/     # Ad type handlers (code, image, plain-text, etc.)
│       ├── GeoTargeting/
│       ├── Links/       # Partnership links
│       ├── Placements/  # Placement engine
│       └── Targeting/   # Targeting engine, frequency manager
├── assets/              # CSS/JS
├── templates/           # Frontend templates (Template_Loader aware)
├── languages/           # i18n
├── docs/                # Internal docs
├── tests/               # PHPUnit + e2e
└── build/               # Release-ready output (gitignored)
```

---

## Git Branches

| Branch | Purpose |
|--------|---------|
| `main` | Stable, latest release (currently `2.8.0`) |
| `2.8.1` | Current dev branch (latest) |
| `2.8.0`, `2.7.0`, `2.6.1`, `2.5.0` | Historical release branches |

---

## Coding Standards — MANDATORY

**Before ANY code change, invoke `/wp-plugin-development` skill.** Single source of truth for Wbcom plugin dev: backend, frontend, REST, DB, CI, admin UI, testing.

### Quick Reference
- **Text domain:** `wb-ads-rotator-with-split-test`
- **CSS prefix:** `.wbarst-` (BEM-lite)
- **JS namespace:** `wbARST` / `window.wbARST`
- **REST namespace:** `wb-ads/v1`
- **DB prefix:** `wbarst_` / option prefix `wb_ads_rotator_`
- **Escaping:** `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` on ALL output
- **Sanitization:** `sanitize_text_field( wp_unslash( $_REQUEST['...'] ) )` — never raw `$_REQUEST`
- **Capability checks:** `current_user_can()` required on every AJAX/REST handler
- **Nonce verification:** required on every form/AJAX
- **No inline styles.** All CSS in `.css` files.
- **Browser verification:** Playwright screenshot before marking any frontend change done

---

## Free vs Pro Boundary

This free plugin provides:
- Ad rotation with split-test capability
- Impression/click tracking
- Basic statistics
- Geo-targeting (basic)
- Placement engine
- Targeting engine
- REST API for ads/analytics/links/settings

Pro (`wb-ad-manager-pro`) adds: classifieds, advertiser portal, wallet/payments, campaigns, advanced analytics, A/B winner auto-selection, multi-variate testing.

**Never duplicate code between the two plugins.** Pro extends Free via hooks/filters — if a hook is needed, add it here first.

---

## Verify-Per-Item Rule

Every plan item touching frontend/CSS/templates MUST be browser-verified before marked done:
1. Implement
2. Playwright screenshot at 390px (mobile) AND desktop
3. Verify matches intent
4. Only then mark done

Code quality checks (WPCS/PHPStan/PHPUnit) passing ≠ feature done.

---

## Release Workflow

Use `/wp-plugin-release` when ready to ship. Build output goes to `build/` (gitignored; `distignore` controls what ships).

**Release discipline** (from global memory):
- `distignore` is plugin-root-relative
- The builder's runtime-reference scanner catches forgot-to-ship bugs
- Test on real WP before shipping the zip
