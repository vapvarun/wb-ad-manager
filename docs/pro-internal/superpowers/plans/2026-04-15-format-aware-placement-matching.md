# Format-Aware Placement Matching — Unified Plan

**Date:** 2026-04-15
**Scope:** wb-ads-rotator-with-split-test (free) + wb-ad-manager-pro (pro)
**Branches:** free `2.8.0`, pro `1.5.0`
**Status:** Planning

---

## Working agreement (non-negotiable)

1. **Nothing ships that isn't in this plan.** If a new concern surfaces
   mid-implementation, the plan gets amended first, then the code lands
   under a named phase. Documented phases so far: 1, A, B, C, D, E, F,
   G, H, I, J.
2. **No duplicate code paths.** New surfaces delegate to existing
   helpers (`wbam_ad_fits_placement`, `Frequency_Manager::get_weighted_random`,
   `Placement_Engine::render_ad`). The matching/rotation/render logic
   lives in exactly one place each.
3. **Feature-flagged rollout.** Every user-visible behavior change is
   gated (`wbam_format_matching_enabled`, `wbam_placement_render_mode`)
   so site owners opt in after reviewing the state on their install.
4. **Professional parity.** Site owner and advertiser see the same
   numbers. If an impression is counted toward billing, both surfaces
   show it; if an ad is filtered out at render time, both surfaces
   report why (format mismatch, session cap, cap exhaustion).

## Context

Placements today are matched to ads purely by **slug** (allow-list on the package + checkboxes on the ad). Nothing in the system knows:

- Whether an ad is **responsive** (fills any width) or **fixed-size** (728×90, 300×600, etc.)
- Whether a placement **expects** a specific size (header = 728×90) or accepts anything
- Therefore whether a given ad **fits** a given placement

Consequence: advertisers paste a 300×600 skyscraper image and tick "Header", the system renders it, layout breaks, customer complains.

We already landed phase 1 (per-page dedup + responsive flag + `.wbam-ad-slot` wrapper) in free commit `45821b8`. This plan defines the format-matching layer that builds on top of it.

**Goal state:** advertiser uploads a creative → system automatically knows which placements it fits → package purchase shows only compatible placements → render-time guard silently skips mismatched ads.

**Non-goal:** AI-based creative resizing. Out of scope for this cycle.

---

## The canonical format taxonomy

Single source of truth. Hard-coded constants in free plugin. Every ad and every placement declares one of these.

| Slug | Dimensions | Responsive | Typical use |
|---|---|---|---|
| `leaderboard` | 728×90 | no | Desktop header, in-content top |
| `large-leaderboard` | 970×90 | no | Wide desktop header |
| `banner` | 468×60 | no | Legacy header / small content |
| `mobile-banner` | 320×50 | no | Mobile sticky, mobile header |
| `mobile-large-banner` | 320×100 | no | Mobile in-content |
| `medium-rectangle` | 300×250 | no | Sidebar, in-content, between-items |
| `large-rectangle` | 336×280 | no | Sidebar (wide), in-content |
| `skyscraper` | 160×600 | no | Sidebar (narrow) |
| `wide-skyscraper` | 300×600 | no | Sidebar (half-page) |
| `square` | 250×250 | no | Sidebar small |
| `responsive` | — | yes | Fills any container |
| `custom` | user-defined W×H | no | Explicitly set by advertiser |

**Matching rule:**
- An ad with format `responsive` fits **every** placement.
- An ad with a fixed format fits a placement whose `accepted_formats` list includes that format OR whose format is `responsive`.
- An ad with `custom` dimensions fits a placement only if its W×H matches (within tolerance) one of the placement's accepted formats.

**Tolerance:** exact match by default. Filterable via `wbam_format_fit_tolerance` for sites that want fuzzy matching.

---

## Single source of truth: `wbam_ad_fits_placement()`

One function. Every surface that needs to know "can this ad go here?" calls it.

```php
/**
 * Whether the given ad is compatible with the given placement slug.
 * Considers responsive flag, ad format, placement accepted_formats.
 *
 * @since 2.8.1
 * @param int    $ad_id          Ad post ID.
 * @param string $placement_slug Placement slug.
 * @return bool
 */
function wbam_ad_fits_placement( int $ad_id, string $placement_slug ): bool;
```

Lives in `wb-ads-rotator-with-split-test/includes/Core/functions.php`.

---

## Architecture changes, per surface

### 1. Free plugin — `Placement_Interface`

Add two methods with default implementations via an abstract base class (most placements don't need to override):

```php
public function get_accepted_formats(): array;  // e.g. ['leaderboard']
public function get_recommended_size(): array;  // e.g. ['w'=>728,'h'=>90]
```

Existing placements get concrete declarations:

| Placement | Accepted formats |
|---|---|
| Header | `leaderboard`, `large-leaderboard`, `banner`, `responsive` |
| Footer | `leaderboard`, `responsive` |
| Before/After Content | `leaderboard`, `medium-rectangle`, `large-rectangle`, `responsive` |
| After Paragraph | `medium-rectangle`, `large-rectangle`, `responsive` |
| Widget | `medium-rectangle`, `skyscraper`, `wide-skyscraper`, `square`, `responsive` |
| Archive Before/After | `leaderboard`, `responsive` |
| Sticky/Floating | `mobile-banner`, `mobile-large-banner`, `responsive` |
| Popup/Modal | `medium-rectangle`, `large-rectangle`, `responsive` |
| Comments | `medium-rectangle`, `responsive` |
| BuddyPress Activity | `medium-rectangle`, `responsive` |
| BuddyPress Before/After Members/Groups | `leaderboard`, `medium-rectangle`, `responsive` |
| Jetonomy Sidebar Top/After About/Bottom | `medium-rectangle`, `wide-skyscraper`, `skyscraper`, `responsive` |
| Jetonomy After Topic Body | `leaderboard`, `medium-rectangle`, `responsive` |
| Jetonomy Before/After Replies | `leaderboard`, `responsive` |
| Jetonomy Between Replies | `leaderboard`, `medium-rectangle`, `responsive` |

### 2. Free plugin — Ad model

Two new post-meta keys:
- `_wbam_ad_format` — canonical format slug (from taxonomy above) OR `responsive`
- `_wbam_ad_width` / `_wbam_ad_height` — dimensions (for `custom` format or for display in admin)

Already landed in phase 1: `_wbam_is_responsive`. Keep it as the authoritative "responsive?" signal; if `1`, `_wbam_ad_format` = `responsive` (kept in sync on save).

### 3. Free plugin — Auto-detection on save

When ad type = image and `image_url` is a local media attachment:
- Pull `wp_get_attachment_image_src()` → get W×H
- Match W×H against taxonomy → pick slug (or `custom` if no exact match)
- Save `_wbam_ad_format` + dimensions

When image is external URL: skip auto-detect, ask advertiser to pick or tick "Responsive".

For code/AdSense ads:
- AdSense `auto` format → automatically `responsive`
- Code ad → default `responsive`, user can override via the new admin dropdown

### 4. Free plugin — Admin ad edit UI

Add under the existing "Responsive ad" checkbox:

```
Ad Format: [ dropdown with taxonomy | Custom W × H ]
   ↳ Auto-detected from image: 728×90 (leaderboard)   (if applicable)
```

If "Responsive ad" is ticked, the format dropdown is disabled and reads "Responsive".

Warn banner below placement picker:
> ⚠ This 300×600 ad (wide-skyscraper) is not compatible with the selected placements: Header, After Topic. Untick them or mark the ad as Responsive.

### 5. Free plugin — Render-time filter

Extend `Placement_Guard` (pro) and the core `Placement_Engine` flow:

In `Placement_Engine::get_ads_for_placement()` after the existing package filter, add:
```php
$ads = array_filter( $ads, fn( $ad_id ) => wbam_ad_fits_placement( $ad_id, $placement_id ) );
```

Silent skip — same behavior as the package guard. No error surface to the visitor.

### 6. Pro plugin — Ad submission form (advertiser portal)

When advertiser uploads image:
- Detect dimensions client-side (JS `FileReader` or wait until saved server-side)
- After save, show only packages whose accepted-formats intersect with the ad's format
- After package selected, show only placements whose accepted-formats include the ad's format

Replace the 22-checkbox grid with:
> Your ad is a **728×90 leaderboard** — it will automatically show in these placements: Header, Footer, After Topic, Before Replies. [Advanced: pick specific slots]

### 7. Pro plugin — Package editor

Replace "Allowed Placements" (22 checkboxes) with a two-level picker:
- **Step 1:** "Allowed ad formats" (checkboxes for the 12 taxonomy entries) — this is what the package really sells.
- **Step 2 (collapsed by default, "Advanced"):** Placement-by-placement override — exclude specific placements even if their format matches.

Data model: add `allowed_formats` column to `wbam_packages`; keep existing `placements` as the per-placement exclusion override. Empty `allowed_formats` = all formats (matches existing "empty placements = all").

---

## Migration path

### For existing ads (free)
Backfill script runs once on plugin upgrade:
1. For each ad with `type=image`: read image URL, if local attachment → read dimensions → assign format. If external → leave format empty, mark as needs-attention.
2. For each ad with `type=code` or `adsense` without responsive flag: set `_wbam_is_responsive=1` (safe default, preserves current rendering).
3. For each ad with `type=rich_content`: set responsive (rich HTML is fluid by nature).

### For existing packages (pro)
Migration script runs on plugin upgrade:
1. Read existing `placements` column (slug list).
2. Compute the union of all `accepted_formats` from those placement slugs → that becomes `allowed_formats`.
3. Keep the original `placements` column intact as the exclusion override.

Result: existing packages behave exactly as before, then admins can gradually move to format-based.

### Feature flag
Entire format-matching layer gates on a site option `wbam_format_matching_enabled` (default `false` until a plugin version that's confident in the migration). Allows ship-and-watch rollout.

---

## Files to create / modify

### Create
- `wb-ads-rotator-with-split-test/includes/Core/class-ad-formats.php` — taxonomy constants + helpers
- `wb-ads-rotator-with-split-test/includes/Core/class-format-detector.php` — auto-detect from image/type
- `wb-ads-rotator-with-split-test/includes/Migrations/class-migration-format-backfill.php`
- `wb-ad-manager-pro/includes/Migrations/class-migration-package-formats.php`

### Modify
- `wb-ads-rotator-with-split-test/includes/Modules/Placements/interface-placement.php` — add two methods
- `wb-ads-rotator-with-split-test/includes/Modules/Placements/` — every placement class (~30 files) — declare `accepted_formats`
- `wb-ads-rotator-with-split-test/includes/Modules/Placements/class-placement-engine.php` — integrate filter
- `wb-ads-rotator-with-split-test/includes/Core/functions.php` — `wbam_ad_fits_placement()`
- `wb-ads-rotator-with-split-test/includes/Admin/class-admin.php` — format dropdown + warn banner
- `wb-ads-rotator-with-split-test/assets/js/admin.js` — toggle format dropdown when Responsive ticks
- `wb-ad-manager-pro/includes/Core/class-pro-admin.php` — package editor two-level picker
- `wb-ad-manager-pro/includes/Modules/Packages/class-package.php` — `allowed_formats` field + `has_format()`
- `wb-ad-manager-pro/includes/Modules/Packages/class-placement-guard.php` — add format check
- `wb-ad-manager-pro/templates/portal/ad-form.php` — auto-match placements by format
- `wb-ad-manager-pro/includes/Core/class-installer.php` — schema + migration trigger

---

## Execution phases

Each phase is independently testable and shippable.

### Phase A — Taxonomy + helpers (free only)
- Create `class-ad-formats.php` with constants + `get_format_by_dimensions()`
- Create `wbam_ad_fits_placement()` function (returns `true` for everything initially — no data to match against yet)
- Unit-test the helpers
- No user-visible change

### Phase B — Placement declarations (free only)
- Add `get_accepted_formats()` / `get_recommended_size()` to `Placement_Interface` with responsive defaults
- Declare concrete formats on all existing placements
- Unit-test: every registered placement returns at least one accepted format

### Phase C — Ad format UI + detection (free only)
- Admin metabox: format dropdown + auto-detect hint
- Save `_wbam_ad_format` / `_wbam_ad_width` / `_wbam_ad_height`
- JS toggle: Responsive ticked → disable format dropdown
- Backfill migration for existing ads
- Browser verify: create ad, check meta is saved

### Phase D — Render-time filter (free only)
- Wire `wbam_ad_fits_placement()` into `Placement_Engine::get_ads_for_placement()`
- Feature flag: `wbam_format_matching_enabled`
- Browser verify: mismatched ad silently skipped; matched ad renders

### Phase E — Pro admin: package format picker
- Schema change: add `allowed_formats` column
- Package editor: two-level picker UI
- Placement_Guard adds format check
- Migration of existing packages
- Browser verify: package with "leaderboard only" correctly blocks a 300×600 ad

### Phase F — Pro advertiser portal: auto-match flow
- Ad-form.php: detect upload size → filter compatible packages → filter placements
- Replace manual placement grid with "Your ad will show in X, Y, Z" summary
- Browser verify: upload 728×90 → only leaderboard-compatible packages show

### Phase H — Slot inventory model (AdSense-parallel)

**Principle:** a placement is *inventory*, not a container. One hook
invocation = one creative render. Multiple advertisers targeting the
same slot rotate; they never stack.

Already shipped in Phase D commit `430ac21` as a bug-fix patch for the
"2 header ads" issue — but only the mechanical rotation, not the
commercial surface. This phase captures the professional framing
so site owners and advertisers see the same mental model:

- **Site owner UI:** each slot card shows "currently competing for
  this slot: N ads", fill rate, win rate per advertiser, revenue
  attribution. Like AdSense "Ad units" inventory page.
- **Advertiser UI:** for each active creative, a "Slots reached"
  badge showing which placements the creative won an impression on
  in the last 24h / 7d, plus a "share of voice" percentage per slot.
  Like AdSense "Performance by ad unit" report.
- **Filter:** `wbam_placement_render_mode` returning `'stack'` for
  rare slots that legitimately want multiple renders per page
  (widget areas, between_replies with its own frequency counter).
  Already implemented; this phase formalizes the contract in docs.
- **Weight transparency:** make the _wbam_priority slider show its
  effect on win rate ("Priority 5 = ~33% share in a 3-way tie") so
  advertisers understand why the higher-priority creative wins more.

### Phase I — Pacing + frequency caps

Prevent advertiser burnout AND prevent empty slots when high-priority
ads exhaust their session limit early.

- **Per-slot fill fallback:** if the winning ad is session-capped for
  this visitor, fall through to the next-highest-priority ad rather
  than leaving the slot empty.
- **Budget-aware pacing (pro):** CPM/CPC campaigns with a daily
  budget get smoothed delivery across the day, not burned in the
  first hour. Leverages the existing Billing_Manager hourly cron.
- **Frequency caps per visitor:** session_limit already exists
  per-creative; add a per-advertiser cap ("max 3 impressions from
  this advertiser per session") and a per-campaign cap.

### Phase J — Billing + impression transparency

Advertisers pay per impression / click / flat. The impression record
must be defensible: advertiser sees every impression their ad earned,
site owner sees every impression they billed for, numbers reconcile.

- **Impression ledger:** append-only log keyed by (ad_id, placement,
  timestamp, visitor_hash, session_id). Already partially exists in
  the analytics table; this phase formalizes the schema + retention.
- **Billing proof surface:** advertiser portal shows, per campaign,
  the impressions that were counted toward billing (with timestamps
  + placement slug). Admin can audit any advertiser's billing.
- **Disputed-impression refund flow:** if an impression is found to
  be invalid (bot, self-view, cached), admin can issue a partial
  refund that reconciles both sides without breaking the ledger.

### Phase G — Contextual onboarding + next-step guidance

Broken into sub-phases so each lands independently and is separately
verifiable. Shipped so far in `932173d` (pro): **G.1 — next-step banner**.
Sub-phases below are the remaining onboarding work.

### Phase G.2 — First-install pointers (FREE plugin)

WP-pointer-based contextual tooltips that fire once per user on the
first visit to specific admin screens after upgrading. Strictly
scoped to free-plugin UI — zero dependency on pro.

- **Scope:** free admin screens only (`edit.php?post_type=wbam-ad`,
  Ad Settings, Setup Wizard)
- **Pointers shipped:**
  1. Ad edit — **Sizing section** (points to the Responsive/Fixed
     radio picker). "Pick how this ad should be sized — responsive
     fills any slot; fixed only shows where the size matches."
  2. Ad edit — **Priority slider** ("This controls your win share
     when multiple ads compete for the same slot.")
  3. Ad edit — **Placement cards** ("These are the slots the ad
     can render in. The system auto-filters based on the sizing
     choice above.")
- **Delivery:** `WBAM\Admin\First_Install_Pointers` class, enqueues
  `wp-pointer` script + inline JS defining the pointer array.
  Per-user dismissal via `update_user_meta( 'wbam_pointers_shown' )`
  with one entry per pointer slug. `array_diff` against the
  definition list decides which pointers to emit this request.
- **Feature flag:** `wbam_onboarding_pointers_enabled` option,
  defaults to **on for new installs, off for existing**. Set on
  activation via a flag in `Installer` that reads "is this a fresh
  install or an upgrade" — fresh = turn pointers on, upgrade = leave
  off so power users aren't annoyed.
- **Files:**
  - NEW `wb-ads-rotator-with-split-test/includes/Admin/class-first-install-pointers.php`
  - MODIFY `includes/Core/class-plugin.php` (register class on admin_init)
  - MODIFY `includes/Core/class-installer.php` (set default flag)
- **Verification:**
  1. Fresh install → ad edit screen shows sizing pointer, dismiss,
     next pointer appears on next load
  2. Upgrade install → no pointers (flag off by default)
  3. Manual `wp option update wbam_onboarding_pointers_enabled 1` +
     reset user meta → pointers reappear

### Phase G.3 — First-install pointers (PRO plugin)

Parallel to G.2 but scoped strictly to pro screens + pro UI elements.
Different class, different option key, different dismissal meta so
free and pro never entangle.

- **Scope:** pro admin screens only (`wbam-packages`, `wbam-inventory`,
  advertiser portal `[wbam_advertiser_dashboard]`)
- **Pointers shipped:**
  1. Package edit — **Allowed Formats grid** ("Pick creative formats
     advertisers can buy under this package. Placements are an
     optional override below.")
  2. Admin menu — **Slot Inventory** menu item ("See every slot,
     fill rate, and who's winning impressions. AdSense-style
     transparency.")
  3. Advertiser portal — **Slots Reached panel** (per-ad card, first
     visit after the ad has earned an impression). "See exactly
     which placements your ad won and your share of voice per slot."
  4. Advertiser portal — **Billing Proof disclosure** (per-campaign
     card). "Every event counted toward your billing, with timestamp
     and placement."
- **Delivery:** `WBAM_Pro\Admin\First_Install_Pointers` — same
  pattern as G.2, separate namespace, separate option key
  (`wbam_pro_onboarding_pointers_enabled`), separate meta key
  (`wbam_pro_pointers_shown`).
- **Feature flag:** default on for fresh pro installs, off for
  upgrades. Set on activation by checking `get_option( 'wbam_pro_db_version' )`
  existence before the installer runs.
- **Files:**
  - NEW `wb-ad-manager-pro/includes/Admin/class-first-install-pointers.php`
  - MODIFY `includes/Core/class-pro-plugin.php` (register)
  - MODIFY `includes/Core/class-installer.php` (set default flag)
- **Verification:** browser screenshots on each of the 4 pointer
  targets, dismissal persists per user.

### Phase G.4 — Field-level tooltips (both plugins, separate files)

Small info icons next to non-obvious form fields with a click-to-open
popover. Same component used in both plugins but each registers its
own field list.

- **Component:** shared CSS class `.wbam-tip` + tiny JS enqueued from
  each plugin independently (no cross-plugin dependency).
- **Free fields tipped:**
  - Session Limit
  - Priority
  - Responsive / Fixed size toggle
  - Custom W × H input
- **Pro fields tipped:**
  - Package Allowed Formats
  - Package Placement Override
  - Campaign pacing (daily budget derivation)
  - Per-advertiser session cap
  - Per-campaign session cap
- **Delivery:** each plugin owns its own CSS + JS under
  `assets/css/admin-tooltips.css` / `assets/js/admin-tooltips.js`.
- **Verification:** click each tip icon, popover appears with the
  documented copy, closes on outside click or Escape.

### Phase G.5 — List-table empty states (both plugins, separate)

Replace the default WordPress "No items found" on every list table
with a friendly callout that explains the table's purpose + a
primary CTA.

- **Scope:** free admin list tables (Ads, Analytics),
  pro admin list tables (Advertisers, Packages, Campaigns,
  Transactions, Submissions, Classifieds, Audit Log)
- **Per-table copy:** defined in each plugin's existing list-table
  classes via a new `get_empty_state()` method that returns
  `['title', 'body', 'cta_label', 'cta_url']`.
- **Template:** small reusable partial in each plugin.
- **Verification:** visit each list table on an install that has
  zero rows of that type, confirm the empty state renders and the
  CTA lands on the right create screen.

### Phase G.6 — Setup progress dashboard widget (PRO only)

WP dashboard widget (`wp_add_dashboard_widget`) showing a checklist
of the onboarding steps the next-step banner resolver uses:

- [ ] First ad created
- [ ] First package created
- [ ] First advertiser registered
- [ ] Format matching enabled
- [ ] First campaign earned billed impressions
- [ ] Stripe connected via Credits SDK (optional)

Reads the same `Next_Step_Banner::collect_state()` helper from G.1
so the widget and banner never disagree.

- **Files:** NEW `includes/Admin/class-dashboard-widget.php`
- **Verification:** visit `/wp-admin/` — widget renders with correct
  checkmarks based on current install state.

### Phase J.2 — Admin audit view for billing ledger

Site-owner-facing mirror of Phase J's per-advertiser ledger. Same
`Inventory_Dashboard::get_campaign_ledger()` data source, admin
permissions, broader filter options (by advertiser, by placement,
by event type, by date range).

- **Location:** Slot Inventory page (`wbam-inventory`), new
  "Impression Audit" tab.
- **Extra filters vs. advertiser view:** advertiser dropdown,
  placement dropdown, CSV export.
- **Permissions:** `manage_options` only.
- **Files:**
  - MODIFY `includes/Modules/Analytics/class-inventory-dashboard.php`
    (add `render_audit_tab()` + tab-switching in `render()`)
- **Verification:** admin can filter by advertiser #6, sees only
  that advertiser's events; totals match the advertiser's own
  portal ledger for the same window (professional parity).

### Phase J.3 — Dispute / invalid-impression refund flow

Per-row "flag as invalid" control on the admin audit view (J.2).
Flagging writes a **credit adjustment** via the Credits SDK rather
than mutating the append-only `wbam_analytics` table. Both surfaces
(advertiser + admin) still display the original row, plus a small
"refunded" pill.

- **Schema:** new `wbam_invalidation` table with columns
  `id, analytics_id, campaign_id, amount_refunded, reason, admin_id,
  created_at`. Append-only. One row per invalidation action.
- **Refund mechanism:** `Credits::adjust()` on the Credits SDK with
  `reason = 'invalid_impression_refund'`. Idempotency key derived
  from `analytics_id` so re-flagging the same row never double-refunds.
- **UI:** admin audit row → "Flag invalid" button → modal asks for
  reason (bot, self-view, cached, other) → submit posts to
  `admin-post.php?action=wbam_flag_invalid`.
- **Advertiser surface:** billing-proof ledger row gains a
  `.wbam-billing-proof__refunded` pill displaying "Refunded: $X.XXXX
  — reason" when a matching `wbam_invalidation` row exists.
- **Files:**
  - MODIFY `includes/Core/class-installer.php` — add table + migration
  - NEW `includes/Modules/Analytics/class-invalidation-manager.php`
  - MODIFY admin audit render + advertiser ledger render
- **Verification:** flag a row → refund debit posts to wallet →
  both surfaces show the refunded pill → flag same row again → no
  double refund.


The plugin carries 20+ admin screens and 40+ settings. Users get lost.
This phase adds in-context guidance without a separate help hub.

- **Next-step banner** at top of each admin screen, dismissible per-user,
  driven by a `wbam_next_step()` resolver that checks installation state
  (no ads yet → "Create your first ad"; ads but no package → "Create a
  package"; package but no advertiser → "Invite advertisers"; all done →
  no banner). Banner uses a standardized component so every screen looks
  the same.
- **Field-level tooltips** using `aria-describedby` + a small JS popover
  on the information icon next to each non-obvious field (format, session
  limit, targeting, pricing model, allowed_formats, etc.).
- **Empty-state callouts** in every list table ("No ads yet. Here's why
  you'd want one + [Create ad]") instead of the default WP "No items".
- **Setup progress tracker** in the admin dashboard widget — completion
  state for: plugin activated, first ad created, first package created,
  first campaign approved, Stripe connected (pro), first impression
  recorded, first revenue booked.
- **Doc links** at the top-right of each screen deep-linking into the
  existing `docs/` content, opening in a side panel where possible.

Feature-flag: `wbam_onboarding_guidance_enabled` (default on for new
installs, off for existing installs so power users aren't annoyed).

Out of scope here: full interactive tour (Intro.js / Shepherd).
That's a separate investment once the static guidance lands.

---

## Verification checklist

After each phase:
- `php -l` all modified files
- `composer test` (if tests exist for the area)
- Browser screenshot via Playwright on the affected admin screen + frontend render
- Record before/after in `BUILD-LOG.md` per wp-builder convention

Final end-to-end:
1. Admin creates package "Leaderboard Pack" → allowed formats = [leaderboard]
2. Advertiser uploads 728×90 image → picks "Leaderboard Pack" → no placement picker shown → ad auto-activates
3. Visit frontend → ad renders in Header, After Topic Body (both accept leaderboard), not in Sidebar Top (rectangle/skyscraper only)
4. Repeat with a 300×600 image under same package → system refuses submission (format mismatch for package)
5. Repeat with a responsive code ad → renders in every placement

---

## Risks + rollback

| Risk | Mitigation |
|---|---|
| Existing live ads stop rendering after upgrade | Feature flag off by default; migration treats unknown ads as `responsive` (permissive) |
| Format misdetected from external URLs | External URLs → format empty → treated as responsive; admin can manually pick |
| Admin confused by new UI | Keep old placement checkboxes available under "Advanced" disclosure |
| Theme conflict with `.wbam-ad-slot` wrapper | Already shipped in phase 1; CSS is additive and constrained by class |

**Rollback:** flip `wbam_format_matching_enabled` off, everything reverts to slug-only matching. No schema changes except the additive `allowed_formats` column (safe to leave).

---

## Basecamp cards

Create one card per phase in the WB Ad Manager board (44982066) under "Ready for Development". Link back to this plan from each card description.

- [ ] Phase A — Taxonomy + helpers
- [ ] Phase B — Placement declarations
- [ ] Phase C — Ad format UI + detection + backfill
- [ ] Phase D — Render-time filter behind feature flag
- [ ] Phase E — Package format picker + migration
- [x] Phase F — Advertiser portal auto-match flow (`2f38965`)
- [x] Phase H mechanical rotation (`430ac21`)
- [x] Phase H.1 — Slot Inventory dashboard (`2c320fe`)
- [x] Phase H.2 — Slots Reached panel (`26f7b31`)
- [x] Phase H.3 — Priority slider win-share hint (`0225a01`)
- [x] Phase I.1 — Slot fill-fallback (`80d0122`)
- [x] Phase I.2 — Per-advertiser + per-campaign caps (`2eecc07`)
- [x] Phase I.3 — Budget-aware pacing (`41e4314`)
- [x] Phase J — Billing-proof ledger (advertiser view) (`59077d6`)
- [x] Phase G.1 — Next-step banner (`932173d`)
- [ ] **Phase G.2** — First-install pointers (FREE)
- [ ] **Phase G.3** — First-install pointers (PRO)
- [ ] **Phase G.4** — Field-level tooltips (both plugins, separate files)
- [ ] **Phase G.5** — List-table empty states (both plugins, separate)
- [ ] **Phase G.6** — Setup progress dashboard widget (PRO only)
- [ ] **Phase J.2** — Admin audit view for billing ledger
- [ ] **Phase J.3** — Dispute / invalid-impression refund flow

---

## Open questions (need decision before Phase C)

1. **External image URLs** — should we fetch headers to detect dimensions, or always require the advertiser to pick a format? Fetching adds a network call and fails for CORS/auth-protected images. **Recommendation: always ask, never auto-fetch external.**
2. **Rich-content ads** — default to responsive (safe) or require advertiser to pick? **Recommendation: default responsive; most rich HTML is fluid.**
3. **Tolerance for "custom" formats** — exact pixel match or allow ±10%? **Recommendation: exact match; add a filter for sites that want fuzzy.**
4. **Should Packages bundle placements OR formats?** The plan proposes both (formats primary, placements override). Alternative: drop the placements column entirely. **Recommendation: keep both for migration safety; deprecate placements later once format-matching is proven.**
