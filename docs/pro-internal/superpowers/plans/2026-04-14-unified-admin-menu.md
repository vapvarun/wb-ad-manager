# Unified Admin Menu + Settings Architecture (Free + Pro)

**Date:** 2026-04-14
**Status:** Planning — no code yet.
**Target release:** 1.6.0 (NOT 1.5.0 — this is a Phase-2 scope architectural shift)
**Plan reference:** `~/.claude/plans/generic-churning-aurora.md` — this document is the detailed execution of that plan's Phase 2 admin-UX workstream.

---

## Context

Today WB Ad Manager ships with **four separate admin top-level menus** and **two separate settings pages** across the Free + Pro plugin pair. The product is one product in the user's mind; the admin UI makes it look like four. This has downstream effects: support tickets say things like "I can't find X" when X lives under a different top-level; when Free adds a setting (e.g. Placement Engine gating), admins must save it in one place while related Pro settings (Package placements) save in another; the settings API has drifted — Free uses raw option pages, Pro uses the custom tab system with the new `_active_tab` contract shipped in 1.5.0 (W1.4).

**Why now:** We just shipped W1.4 (tab contract). The sanitizer layer is ready to host Free settings. The next step is bringing Free settings into the same surface instead of keeping them on their own WP Options page. Parallel work streams are cheap here because the 1.5.0 credits-SDK migration already moved Pro off its worst legacy (Wallet_Manager, Transaction model), so the admin menu is the next-biggest structural mess.

---

## Current State — Menu Inventory

### Free plugin (`wb-ads-rotator-with-split-test`)

All parented under the `edit.php?post_type=wbam-ad` CPT menu.

| Slug | Title | Priority | Source |
|---|---|---|---|
| (CPT default) | WB Ad Manager (post list) | — | CPT registration |
| `wbam-settings` | Ad Settings | 25 | `includes/Admin/class-settings.php` |
| `wbam-help` | Help & Docs | 99 | `includes/Admin/class-help-docs.php` |
| `wbam-upgrade` | Upgrade to PRO | 100 | `includes/Admin/class-upgrade-pro.php` |

**Plus separate top-levels:**
- `wbam-links` (add_menu_page, priority 21) — Links Pro
  - `wbam-links` default — Link list
  - `wbam-links-partnership` (priority 22) — Partnership

**Plus standalone admin pages:**
- Setup Wizard (registered as admin page, not in menu) — `class-setup-wizard.php`

### Pro plugin (`wb-ad-manager-pro`)

Two separate hooks, confusing priority ordering.

**`add_menu_items` (admin_menu priority 20):**

| Parent | Slug | Title |
|---|---|---|
| `edit.php?post_type=wbam-ad` | `wbam-analytics` | Analytics |
| `edit.php?post_type=wbam-ad` | `wbam-campaigns` | Campaigns |
| `edit.php?post_type=wbam-ad` | `wbam-submissions` | Submissions (+ pending badge) |
| **top-level** | `wbam-classifieds` | Classifieds (+ pending badge) |
| └ `wbam-classifieds` | `wbam-classifieds` | All Classifieds |
| └ `wbam-classifieds` | `edit-tags.php?taxonomy=wbam-classified-cat` | Categories |
| └ `wbam-classifieds` | `edit-tags.php?taxonomy=wbam-classified-loc` | Locations |
| └ `wbam-classifieds` | `wbam-classified-inquiries` | Inquiries |
| └ `wbam-classifieds` | `wbam-custom-fields` | Custom Fields (conditional) |
| └ `wbam-classifieds` | `wbam-reviews` | Reviews (+ pending badge) |
| **top-level** | `wbam-advertisers` | Advertisers |
| └ `wbam-advertisers` | `wbam-advertisers` | All Advertisers |
| └ `wbam-advertisers` | `wbam-packages` | Packages |
| └ `wbam-advertisers` | `wbam-transactions` | Transactions |
| └ `wbam-advertisers` | `wbam-memberships` | Membership Plans |

**`add_settings_menu_items` (admin_menu priority 22):**

Registers the Pro Settings page at `edit.php?post_type=wbam-ad&page=wbam-pro-settings` with tabs: General, Analytics, Modules, Classifieds, Emails, Pages, Geolocation, Credits.

### Count

**Top-level menus today: 4** (WB Ad Manager CPT, Links, Classifieds, Advertisers)
**Settings pages today: 2** (Free "Ad Settings" + Pro "Pro Settings") each with their own save handlers
**Tabs today: 9** (Free has 1 page, no tabs; Pro has 8 tabs)

---

## Target State — One Menu, Consistent Tab Contract

```
WB Ad Manager                              ← one top-level (current CPT menu)
├── Ads                                    ← CPT post list (default)
├── Submissions (Pro)              [badge]
├── Advertisers (Pro)
├── Packages (Pro)
├── Campaigns (Pro)
├── Classifieds (Pro)              [badge]
│   ├── All Classifieds
│   ├── Categories
│   ├── Locations
│   ├── Custom Fields              [Pro, conditional]
│   ├── Reviews                    [Pro, conditional, badge]
│   └── Inquiries
├── Links
│   ├── All Links
│   └── Partnership
├── Analytics
│   ├── Overview (Pro)
│   └── Transactions (Pro)
├── Settings                               ← ONE page, tabs below
│   ├── General         (unified: Free + Pro General merged)
│   ├── Display         (Free: placements, rotation, rendering)
│   ├── Analytics       (Pro)
│   ├── Modules         (Pro)
│   ├── Classifieds     (Pro)
│   ├── Emails          (Pro)
│   ├── Pages           (Pro)
│   ├── Geolocation     (Pro)
│   └── Credits         (Pro)
├── Setup Wizard                           ← linked from Settings > General
├── Help & Docs
└── Upgrade to Pro                 [Free-only, hidden when Pro active]
```

**Top-level menus after: 1** (down from 4).
**Settings pages after: 1** (down from 2) with every tab using the `_active_tab` contract from W1.4.
**Classifieds and Advertisers keep their own first-class children** because they're CRUD surfaces with their own entity lists — they're not settings. They move from top-level to submenu of the one main menu.

---

## Design Decisions (Non-Negotiables)

1. **One menu slug as the canonical parent** — use `edit.php?post_type=wbam-ad` (existing CPT menu). This preserves every existing admin bookmark. Alternative was a fresh `wbam-dashboard` top-level, rejected because it would break 100% of saved URLs.
2. **`_active_tab` contract on every Settings tab** — extend the Pro 1.5.0 work (W1.4) to cover Free settings when they move in. No tab gets a pass. `get_field_types()` map grows to include Free keys.
3. **One sanitizer, one option row per scope** — `wbam_pro_settings` stays the authoritative option for Pro-owned keys; Free settings keep their existing option keys (`wbam_settings`, etc.) but both register through the same Settings page via distinct sections. No merging option rows — too risky on existing sites.
4. **Backward compatibility via `add_submenu_page` redirects** — every removed menu slug (e.g. old `wbam-classifieds` top-level, `wbam-settings` old page) gets a submenu registration that `wp_safe_redirect`s to the new URL with a one-time admin notice.
5. **Badge counts (pending submissions, pending reviews, inquiries) preserved** — the reorg must not drop the `<span class="awaiting-mod">N</span>` UX. Extract badge rendering into a helper.
6. **Capability preservation** — every moved page keeps its current capability requirement. No accidental privilege widening.
7. **All styling in `backend.css`** — admin CSS, WBAM prefix, BEM-lite, tokens. No inline styles. Per `wp-plugin-development` skill Part 2 + Part 6.
8. **PHPUnit acceptance test per card** — write the test FIRST (RED), then move menu, then test passes (GREEN). Per skill Part 7.2.

---

## Task Breakdown — Cards (Basecamp-sized)

Each card is **one logical move** with a test, implementation, and browser verification. Sized so a single session can finish it. Cards ordered for minimum risk (reversible first, irreversible last).

### Card 1 — Foundation: Menu_Registry class

**Scope.** Introduce `WBAM_Pro\Admin\Menu_Registry` (new file `includes/Admin/class-menu-registry.php`). Single class that every menu registration across Free + Pro must call. Holds: parent slug, capability, cap helper, legacy-redirect helper, badge-count helper.

**Acceptance test.** `Menu_RegistryTest::test_register_returns_hook_suffix()`, `test_legacy_redirect_fires_once()`.

**Impact.** No user-visible change yet. Everything else in this plan depends on it.

**Files.** New `includes/Admin/class-menu-registry.php`. Loader entry in `class-pro-plugin.php`.

**Commit message.** `Menu unification 1/N: introduce Menu_Registry helper.`

---

### Card 2 — Move Pro Submissions, Analytics, Campaigns into the canonical parent

**Scope.** These are already parented under `edit.php?post_type=wbam-ad` today. Only change: re-register via `Menu_Registry` so we have a single source of truth. Capture their order and badge count in the registry's central ordering logic.

**Acceptance test.** `Menu_SubmissionsTest::test_submissions_page_renders_under_canonical_parent()`, `test_pending_badge_count_matches_db()`.

**Browser verify.** Screenshot admin left-sidebar — confirm the 3 items appear in expected order with correct badge.

**Commit message.** `Menu unification 2/N: move Submissions/Analytics/Campaigns to registry.`

---

### Card 3 — Demote Classifieds from top-level to submenu

**Scope.** Remove `add_menu_page('wbam-classifieds', ...)` call. Re-register the same slug via `add_submenu_page` under the canonical parent. Preserve the 6 child submenus. Preserve the pending badge. Keep the slug identical (`wbam-classifieds`) so bookmarks and external links work.

**Acceptance test.** `Menu_ClassifiedsTest::test_classifieds_appears_under_canonical_parent()`, `test_classifieds_children_preserved()`, `test_direct_url_still_resolves()`.

**Browser verify.** Admin sidebar — Classifieds listed as submenu, not top-level. Click each child — page renders. Old bookmark `wp-admin/admin.php?page=wbam-classifieds` still opens.

**Risk.** Admin bookmark screenshots in customer docs — unchanged URL handles this.

**Commit message.** `Menu unification 3/N: demote Classifieds to submenu.`

---

### Card 4 — Demote Advertisers from top-level to submenu

**Scope.** Same shape as Card 3 for `wbam-advertisers`. Children (All Advertisers, Packages, Transactions, Memberships) preserved.

**Acceptance test.** `Menu_AdvertisersTest::test_advertisers_under_canonical_parent()`, `test_packages_transactions_memberships_preserved()`.

**Browser verify.** Admin sidebar — Advertisers submenu with 4 children. Each loads. Old URLs still work.

**Commit message.** `Menu unification 4/N: demote Advertisers to submenu.`

---

### Card 5 — Move Links into the canonical parent

**Scope.** In free plugin: remove top-level `add_menu_page('wbam-links')`. Move to submenu of canonical parent. Partnership child preserved.

**Cross-repo.** This touches the Free plugin. Pro has a hard dependency on Free being at ≥ 2.8.0 after this — enforce via `readme.txt` requires header update.

**Acceptance test** (Free plugin test suite): `Menu_LinksTest::test_links_under_canonical_parent()`.

**Browser verify.** Admin sidebar shows Links as submenu, not top-level. Links list renders, Partnership renders.

**Commit message.** `Menu unification 5/N: move Links from top-level to submenu.`

---

### Card 6 — One Settings page, tab contract on every tab

**Scope.** Unify the two settings pages. Free's "Ad Settings" page (`wbam-settings`) disappears as a distinct page; its sections become **tabs within the Pro Settings page** (`wbam-pro-settings`). Rename menu label to just "Settings". Every tab emits `_active_tab` + `_tab_fields[]` per the W1.4 contract — Free sections included.

**Sub-steps:**
- 6a. Catalog every field on Free's Ad Settings page; extend `get_field_types()` in `class-pro-admin.php` to cover them.
- 6b. Port Free render functions into new methods on `Pro_Admin` (`render_display_settings`, `render_rotation_settings`, etc.) — or keep them in Free but call via `do_action('wbam_render_settings_tab', 'display')` so Free doesn't need to know the Pro admin class.
- 6c. Register Free's options (`wbam_settings`) with the unified sanitizer so save writes to both Pro and Free option rows correctly.
- 6d. Legacy `wbam-settings` page slug registered as a thin redirect to `wbam-pro-settings&tab=display`.

**Acceptance test.** `Settings_UnificationTest::test_free_display_tab_renders_under_pro_settings()`, `test_free_display_tab_save_updates_wbam_settings_option()`, `test_pro_settings_untouched_when_free_tab_saved()`, `test_legacy_wbam_settings_url_redirects()`.

**Browser verify.** Settings menu → single page with 9+ tabs. Save each tab. Reload. Values stable. Uncheck checkboxes on any tab — persisted as `false`.

**Risk.** Highest of the plan. Execute last or with feature flag.

**Commit message.** `Menu unification 6/N: unified Settings page with tab contract.`

---

### Card 7 — Upgrade-to-Pro menu visibility

**Scope.** Free's "Upgrade to PRO" submenu (`wbam-upgrade`) should hide when Pro is active. Currently both plugins register independently and Pro doesn't know to suppress. Add a filter in Free that lets Pro silence it.

**Acceptance test.** `Upgrade_Menu_VisibilityTest::test_upgrade_submenu_absent_when_pro_active()`, `test_upgrade_submenu_present_when_pro_inactive()`.

**Browser verify.** With Pro active — no "Upgrade" link. Deactivate Pro — link appears. Reactivate — link gone.

**Commit message.** `Menu unification 7/N: hide Upgrade-to-Pro when Pro is active.`

---

### Card 8 — Setup Wizard + Help & Docs placement

**Scope.** Free's Help & Docs (`wbam-help`, priority 99) stays visible; Pro doesn't duplicate it. Setup Wizard gets a visible entry point from Settings > General ("Re-run setup") rather than being reachable only via direct URL.

**Acceptance test.** `Setup_Wizard_LinkTest::test_general_settings_page_has_rerun_wizard_link()`.

**Browser verify.** Settings > General shows a "Run setup wizard" button. Click — wizard loads. Help & Docs still in sidebar.

**Commit message.** `Menu unification 8/N: Setup Wizard entry point + Help placement.`

---

### Card 9 — Admin UX pass against the skill (Part 6 rulebook)

**Scope.** Retroactive audit of the rewritten menu + settings against `wp-plugin-development` skill Part 6 admin-UX rulebook. Apply: page-header pattern (title + description + actions) to every landing page; `settings-card` pattern to every section; Lucide icons everywhere; toast notifications for save feedback; skeleton loaders for async Analytics widgets. Scoped CSS only.

**Acceptance test.** Visual — no PHPUnit assertion. Playwright screenshots at 390px + 1440px viewports.

**Browser verify.** Before/after screenshots for every page touched. Capture accessibility tree (axe-core) — zero AA violations introduced.

**Commit message.** `Menu unification 9/N: admin UX pass per skill Part 6.`

---

### Card 10 — Documentation

**Scope.** Update `CLAUDE.md` with the new menu tree. Update `docs/ARCHITECTURE.md` — Admin UI section. Add screenshots to `docs/website/` via `wbcom-docs` MCP. Write a migration note in readme.txt for customers upgrading from 1.5.x.

**Acceptance test.** Automated link-check on the generated docs.

**Commit message.** `Menu unification 10/N: docs + migration notes.`

---

## Migration / Rollback

- Each card's commit is reversible in isolation (Cards 1–5 fully, Card 6 partial — feature-flag it).
- Option data untouched across the reorg; `wbam_settings` and `wbam_pro_settings` keep their existing shape.
- On activation after upgrade, store `wbam_pro_menu_unified_at = current_time('mysql')` so dashboards/analytics can distinguish pre/post.
- A revert plan: disable Card 6 via `define('WBAM_PRO_LEGACY_SETTINGS_PAGE', true)` — re-registers the old settings page path as a primary route. Keep through 1.6.2, then remove.

## Capacity Estimate

- Card 1: 1 session (~2h)
- Cards 2–4: 1 session each (~1.5h each)
- Card 5: cross-repo — 1 session including Free PR (~2h)
- Card 6: 2 sessions (planning + impl) + 1 session regression (~6h total)
- Cards 7–8: 1 session combined (~2h)
- Card 9: 1 session (~3h) + iteration via screenshots
- Card 10: 1 session (~2h)

**Total: ~22h of focused work. Ship across 1.6.0-beta.1 through 1.6.0-rc.2 per the Phase-2 release plan.**

## Ordering vs 1.5.0 Release

**None of these cards ship in 1.5.0.** 1.5.0 ships with the six bug fixes + two UX fixes already committed on the `1.5.0` branch. This plan is the next major arc after 1.5.0 is tagged and released.

Exception to monitor: if Card 7 (hide Upgrade submenu when Pro active) is genuinely trivial and high-visibility, it could hitchhike into 1.5.0 as a one-file change. Decide after tagging 1.5.0-rc.1 — not before.
