# Plan — Surfaces that still ignore Site Mode

**Date:** 2026-04-19
**Branch:** 1.5.0
**Scope:** Close the gaps where Site Mode / module flags are ignored, so "Classifieds only, no ads, no link" (and the other modes) collapse the UI cleanly end-to-end.

---

## Why

`Site_Mode::apply()` already flips module flags and capability caps correctly. Most navigation, tabs, and forms already gate on `Settings_Helper::is_module_enabled()` / `wbam_can()`. But a handful of surfaces render ad/campaign/billing chrome regardless of mode. Verified live in Classifieds mode during the 2026-04-19 session:

- Overview stat cards still show Impressions / Clicks / Active Campaigns / Total Spent
- Overview Recent Activity still shows "Ad X created" entries
- Overview "Active Campaigns" section renders if rows exist
- Change Mode dialog description for Classifieds says "May or may not have ads" — inaccurate, the mode is classifieds-only by design
- Admin → Analytics and Admin → Revenue submenus render in Classifieds mode
- The "onboarding steps" card (Create Your First Ad) on Overview is ad-only copy

Everything that hides correctly today stays as-is. Everything listed below is a gap.

---

## Guiding principles

1. **Gate at render, not at data.** Never delete ad/campaign history — a mode flip must be reversible. Existing customers who opt-in and later switch back must see their data again.
2. **Module flag + capability cap both respected.** If the card reads ad data, gate on `ad_submissions`. If the card shows money, also gate on `wbam_can('show_billing_ui', $advertiser)`.
3. **Prefer module gates at render for tab-visible content; use caps for contextual UI (buttons, badges, tooltips).**
4. **Single source of truth.** Each surface gets one conditional — no scattered half-gates.
5. **No new helper funcs unless 2+ callers.** Keep the diff narrow; reuse `is_module_enabled` + `wbam_can`.

---

## Gaps & fixes (by surface)

### 1. Overview stat cards (`templates/portal/tabs/overview.php` lines 100–142)

**Current:** Impressions / Clicks / Active Campaigns / Total Spent render unconditionally.
**Target:** Each card gated.

| Card | Gate |
|---|---|
| Total Impressions | `is_module_enabled('ad_submissions') \|\| is_module_enabled('rotation')` |
| Total Clicks | keep existing `wbam_can('show_ctr_clicks', $advertiser)` AND add module gate |
| Active Campaigns | `is_module_enabled('campaigns')` |
| Total Spent | `is_module_enabled('wallet') && wbam_can('show_billing_ui', $advertiser)` |

**In Classifieds mode, replace with:**
- Active Listings (count from `Classified_Manager::count_for_user`)
- Pending Inquiries (count from inquiries table)
- Favorites (count from favorites table)
- Messages (count from messaging module if enabled)

Use the same `.wbam-stats-grid` markup — no new CSS.

### 2. Overview Recent Activity (`templates/portal/tabs/overview.php` line 35, 271–295)

**Current:** `$advertiser->get_recent_activity(5)` returns ad-flavored entries regardless of mode.
**Target:** `Advertiser::get_recent_activity()` filters event types by enabled modules.

Event types in scope:
- `ad_created`, `ad_approved`, `ad_paused` → require `ad_submissions`
- `campaign_*` → require `campaigns`
- `classified_*` → require `classifieds`
- `wallet_*` → require `wallet` + `show_billing_ui`

Add a `wbam_recent_activity_types` filter so extensions can contribute event types.

### 3. Overview "Active Campaigns" table (`overview.php` line 215–267)

**Current:** Renders whenever `$active_campaigns` is non-empty.
**Target:** Wrap in `is_module_enabled('campaigns')` even if rows exist (mode switched after data was created).

### 4. Overview onboarding steps card (`overview.php` line 50–98)

**Current:** Shows "Create Your First Ad" copy unconditionally.
**Target:** Choose copy by mode:
- Ads-enabled mode → existing "Create Your First Ad" steps
- Classifieds mode → "Post Your First Classified" steps (3-step: Add profile detail → Post classified → Respond to inquiries)
- Hybrid (Full) → combined

Gate via `Site_Mode::current()` or module-combo check.

### 5. Change Mode dialog descriptions (`includes/Core/class-site-mode.php` `get_modes()`)

**Current:**
- Publisher → "I place ads on my site myself. No advertiser portal."
- Sponsored → "Advertisers submit ads for me to approve. No billing, no contracts."
- Paid → "Advertisers pay to run ads. Self-serve billing via credits."
- Classifieds → "Users post classified listings. **May or may not have ads.**"  ← **inaccurate**
- Full → "Everything — paid ads plus classifieds plus all features."

**Fix Classifieds description** to:
> "Users post classified listings with featured/bump upgrades. No paid ads, no link partnerships — classifieds-only marketplace."

### 6. Admin submenu cleanup (`includes/Core/class-pro-admin.php` `add_menu_items()`)

**Already gated correctly:**
- Campaigns submenu (line 158)
- Submissions submenu (line 170)
- Slot Inventory (line 146)

**Still rendering in Classifieds mode — add gates:**

| Submenu | Current gate | Fix |
|---|---|---|
| Analytics (line 136) | none | Add `is_module_enabled('ad_submissions') \|\| is_module_enabled('rotation')` |
| Revenue | none | Add `is_module_enabled('wallet') && is_module_enabled('ad_submissions')` |
| A/B Testing | `ab_testing` module | verify (already correct per classifieds preset) |
| Ad Settings | none | Add `is_module_enabled('ad_submissions')` |
| Ad Rotation settings tab | none | Add `is_module_enabled('rotation')` |

### 7. Admin top-level menus

**"Advertisers" top-level menu** — should stay visible in Classifieds mode (classified posters are still advertisers in the data model). No change.

**"Links" top-level menu** — belongs to free plugin Link Cloaker, out of Pro's scope. No change.

### 8. Pro Settings → Pages tab (page-slug settings)

**Current:** Exposes slugs for all pages including ad-related ones.
**Target:** Hide ad-page slug fields when `ad_submissions` off, classified-page slug fields when `classifieds` off.

Row-level gating inside the Pages tab settings renderer.

### 9. Portal empty-state copy (`class-advertiser-shortcodes.php` `render_dashboard`)

**Current:** When a mode-hidden tab is accessed directly via URL, the tab falls through to a generic "This section is unavailable" message.
**Target:** Redirect to Overview tab with a dismissible notice: "The {tab_name} section is not available in {mode_name} mode." Keeps deep-link hygiene.

Gate: if `$tab_module_map[$current_tab]` exists and module is off → redirect + notice.

### 10. `wbam_is_classifieds_enabled()` call sites

**Current:** Already fixed earlier in session — honors `is_module_enabled('classifieds')`.
**Audit:** Grep for direct `'classified'` post-type queries that bypass the helper and route them through it.

---

## Files touched

| File | Reason |
|---|---|
| `templates/portal/tabs/overview.php` | Gaps 1, 2 (partial), 3, 4 |
| `includes/Modules/Advertisers/class-advertiser.php` | Gap 2 (get_recent_activity filter) |
| `includes/Core/class-site-mode.php` | Gap 5 (Classifieds description) |
| `includes/Core/class-pro-admin.php` | Gap 6 (admin submenu gates), Gap 8 (Pages tab row gates) |
| `includes/Modules/Advertisers/class-advertiser-shortcodes.php` | Gap 9 (deep-link redirect) |
| `includes/Modules/Classifieds/class-classified-manager.php` | Gap 1 (count_for_user helper if missing) |

No CSS changes expected — existing `.wbam-stats-grid`, `.wbam-stat-card`, `.wbam-section` reused.

---

## Order of execution (batches by risk class)

**Batch 1 — Pure render gates (zero data risk)**
- Gap 1 (Overview stat cards)
- Gap 3 (Active Campaigns table)
- Gap 6 (Admin submenus: Analytics, Revenue, Ad Settings)
- Gap 5 (Classifieds description text)

**Batch 2 — Data-source-aware (touches query layer)**
- Gap 2 (Recent Activity filter by enabled event types)
- Gap 4 (Onboarding card copy by mode)

**Batch 3 — Routing (touches request lifecycle)**
- Gap 9 (Deep-link redirect + notice)
- Gap 8 (Pages settings row gating)

Commit per batch. Browser-verify per surface in each mode (Classifieds, Sponsored, Publisher, Paid, Full) before marking a batch done.

---

## Verification matrix

For each batch, verify in these five modes:

| Mode | Portal Overview | Portal Tabs | Admin Menu |
|---|---|---|---|
| Publisher | no portal (bypass — mode hides portal entirely) | N/A | ads + analytics, no advertisers, no classifieds |
| Sponsored | ad cards, no money cards | Ads + Analytics only | ads + submissions, no revenue, no wallet |
| Paid | all ad cards incl money | Ads + Campaigns + Wallet + Analytics | full ad menu + revenue |
| Classifieds | classifieds cards, no ad cards | Classifieds + Inquiries + Favorites + Messages | Classifieds top-level, minimal WB Ad Manager |
| Full | all cards | all tabs | all submenus |

Playwright screenshot each mode, file under `docs/superpowers/plans/screenshots/2026-04-19-site-mode-respect/`.

---

## Out of scope (explicit non-goals)

- Link Cloaker menu (owned by free plugin)
- Gutenberg blocks that render ads — these already no-op when ad_submissions off at serve time
- Email templates — deliver based on events that won't fire in hidden modes, so no template gating needed
- Uninstaller / activator — Site Mode is runtime-only, not destructive

---

## Acceptance criteria

1. Switching to Classifieds mode: Overview shows only classifieds-relevant cards + activity; admin menu shows only classifieds-relevant submenus; Change Mode dialog reads "no paid ads, no link partnerships."
2. Switching to Sponsored mode: Overview hides money cards; admin hides Revenue.
3. Switching to Paid mode: everything visible.
4. Direct-link to a hidden tab → redirected to Overview with a notice (no silent 404 / no ugly empty state).
5. Zero data loss on any mode flip (assert by loading ads/campaigns/classifieds in DB before and after).
6. No new fatals / PHPCS errors / PHPStan errors.

---

## Risks

- **Recent Activity filter regression.** Existing callers to `get_recent_activity()` might expect all events; gate via an opt-out arg `['respect_modes' => true]` default-on for portal, off for admin.
- **Stat card count queries.** Classifieds counts come from a different table — must use prepared SQL + match existing scoping (advertiser vs user id).
- **Mode switch race.** If admin flips mode while an advertiser has a wizard open, the wizard submission might reference a disabled module. Handle by validating module on submission server-side (already present for ad_submissions; audit classifieds + wallet).

---

## Not doing in this plan

- Rewriting the onboarding flow
- Adding new Site Modes
- Changing the `_mode_applied` opt-in semantics
- Touching the wallet reservation logic

---

**Owner:** Varun
**Approval gate:** Review this plan before any code. Implement by batch. Browser-verify per item.
