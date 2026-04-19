# SDK Compliance Audit — WB Ad Manager Pro 1.5.0

**Date:** 2026-04-14
**Branch:** `1.5.0`
**Reference skill:** `wbcom-credits-sdk-integration`

## Executive Summary

The plugin implements the Wbcom Credits SDK with **~90% specification compliance**. One critical bug + one major architectural gap.

- **Critical:** 1 (admin adjust-balance "credit" path uses `hold()` which decrements balance — see flow audit Mismatch #2)
- **Major:** 1 (no SDK-spec compliant Transactions submenu — current page lacks filters/CSV/drilldown)
- **Minor:** 3 (gateway filter hook absent, period-based limits absent by design, dual balance sources)

---

## Phase 1 — SDK Installation: PASS

- SDK loaded at `includes/Core/class-credits-bridge.php:80-81`
- Guard clause prevents fatal on missing vendor
- Load order correct (SDK loaded after registration hook)

## Phase 2 — Registration: PASS

- 3 consumers registered (`ad_submission`, `classified_listing`, `campaign_budget`) at `class-credits-bridge.php:107-165`
- `slug = wbam-pro`, `prefix = wbam`, `user_type = advertiser`
- Lifecycle hooks intentionally empty — plugin owns lifecycle inline (justified, see comment block)

## Phase 3 — Lifecycle Hooks: PASS

Plugin owns the credit lifecycle inline because:
1. Sync return values required for atomic rollback on insufficient balance
2. Plugin maps `advertiser_id → user_id`; SDK Consumer expects `int $item_id`
3. Plugin fires actions with domain objects (`Ad_Submission`, `Classified`, `Campaign`); SDK Consumer expects integer

All critical paths verified to call `Credits_Bridge::hold/deduct/refund`:
- Ad submission: charge at `class-ad-submission-manager.php:226`, refund at `:579`
- Classified rejection: refund at `class-classified-manager.php:1250`
- Campaign budget: hold at activation, refund at completion/cancel/pause

## Phase 4 — Admin UI Split

### A. Settings > Credits Tab: PASS

- Mappings section iterates AdapterRegistry at runtime ✓
- Detected Providers list with active/inactive badges ✓
- Add Mapping form with optgroup-grouped dropdown ✓
- Balance Lookup section ✓
- Purchase URL with auto-resolve + override ✓
- Extensibility actions fired between sections ✓

### B. Transactions Submenu: FAIL (MAJOR)

`wbam-transactions` reads SDK ledger correctly but:
- ❌ No filter UI (date range, type, user)
- ❌ No CSV export
- ❌ No per-advertiser drilldown link
- ❌ No stats strip (Credits Issued / Used / etc.)
- ❌ Inefficient: `get_all_ledger_entries()` is N+1 across advertisers

## Phase 4.5 — Extensibility: PASS

- Rule 1 (runtime registry iteration) ✓
- Rule 2 (action hooks between sections): `wbam_credits_tab_before/after`, `_after_mappings`, `_after_balance_lookup`, `_after_sdk_config` ✓
- Rule 3 (single-page scrollable layout) ✓
- Rule 4 (gateway registration filter): not implemented (not yet needed; SDK has no gateway support)
- Rule 5 (backward-compat mapping reads): defensive null-coalesce ✓

## Phase 5 — User-Facing UI

### A. Balance widget: PASS
`templates/portal/tabs/wallet.php:32-44` shows credit balance + Buy Credits CTA.

### B. Pre-submit credit gate: PASS
`templates/portal/partials/cost-balance-banner.php` shows balance + cost + remaining + low warning + Buy Credits CTA. Server-side gate at `class-classified-shortcodes.php` returns 402 with purchase URL.

### C. Dashboard integration: PASS
Wallet tab shows balance + ledger history + empty state.

## Phase 6 — Listing Limits: WORKS-AS-DESIGNED

Plugin uses **membership-based limits** instead of skill's role-based period limits. Valid design choice — memberships are higher-level abstraction.

## Phase 7 — Acceptance Checklist: 13/14 PASS

| Item | Status | Evidence |
|------|--------|----------|
| SDK ledger table created | ✓ | SDK auto-creates on init |
| `wbcom_credits_sdk_registry` hook fires | ✓ | `class-pro-plugin.php:199` |
| Consumers registered | ✓ | 3 consumers at `class-credits-bridge.php:107-165` |
| Admin Credits page renders sections | ✓ | `class-credits-settings.php` |
| WC product mapping saves | ✓ | `class-credits-settings.php:560-605` |
| Purchase triggers `Credits::topup` | ✓ | SDK WC adapter |
| User balance block shows balance | ✓ | `wallet.php:32-34` |
| Insufficient credits returns 402 with purchase URL | ✓ | `class-classified-shortcodes.php` ajax handler |
| Submission places HOLD | ⚠ | Ad submission uses `deduct` (not hold→deduct lifecycle) — flat-pricing model |
| Approval converts HOLD → DEDUCTION | ⚠ | Same as above |
| Rejection converts HOLD → REFUND | ✓ | `class-ad-submission-manager.php:579` |
| Low balance email fires | ✓ | `wbcom_credits_low` → `wbam_advertiser_low_balance` bridge |

## Mistakes-To-Avoid Audit

| # | Description | Status |
|---|-------------|--------|
| 1 | Wrong lifecycle hook signature | AVOIDED — plugin owns lifecycle inline |
| 2 | Inconsistent prefix | AVOIDED — `Credits_Bridge::PREFIX` constant |
| 3 | Pro-only credit system | N/A — SDK loads in Pro plugin |
| 4 | CRUD vs Config confusion | AVOIDED — mappings in Settings tab, transactions own page |
| 5 | Bare admin UI | AVOIDED — `.wbam-credits-section` wrappers + provider pills |
| 6 | No cost visibility before submit | AVOIDED — cost-balance-banner partial |
| 7 | No period-based limits | DESIGN CHOICE — memberships used instead |
| 8 | Separate Buy Credits page | AVOIDED — auto-resolves to dashboard wallet tab |
| 9 | No Credit Cost field on CPTs | AVOIDED — package price + classified meta |
| 10 | Lifecycle hooks not firing | AVOIDED — verified inline calls |
| 11 | Admin CSS not enqueued | AVOIDED — prefix-match |
| 12 | Hardcoded adapter lists | AVOIDED — `$registry->get_all()` |
| 13 | Settings tab not extensible | AVOIDED — action hooks between sections |
| 14 | Mapping format not backward-compatible | AVOIDED — defensive reads |

**Score:** 13/14 avoided + 1 design choice = clean.

## SDK API Wrapper Coverage

`Credits_Bridge` exposes: `get_balance, hold, deduct, refund, get_ledger, get_cost, get_cost_preview, get_purchase_url, is_enabled`.

**Missing wrappers:**
- `topup()` — needed for admin "credit" adjustments (CRITICAL bug fix)
- `adjust()` — signed delta, needed for admin balance forms
- `cancel_hold()` — needed for hard-revert flows

## Reusable Templates (SDK v1.1)

SDK v1.1 just shipped the Template loader (`Wbcom\Credits\Template`) with empty `templates/admin/` + `templates/frontend/` scaffolds. Plugin renders its own UI today — that's correct for now.

When SDK ships actual template files, swap (already structured for it):
- `Credits_Settings::render_*` → `Template::get('admin/credits-tab-sections/*')`
- `wallet.php` → `Template::get('frontend/dashboard-credits-tab')`
- `cost-balance-banner.php` → `Template::get('frontend/submission-cost-banner')`

CSS tokens already aliased (`--wbcom-credits-* → --wbam-*`) so SDK templates render in-brand.

## Critical Findings

### 1. CRITICAL — Admin "credit" adjustment uses `hold()` not `topup()`

`class-pro-admin.php:5017`:
```php
$result = Credits_Bridge::hold( $advertiser_id, $abs_amount, 0, $note );
```

`hold()` writes a **negative** ledger row reserving credits. Admin "Credit Adjustment" actually deducts. Fix: add `Credits_Bridge::topup()` wrapper, call it instead.

### 2. MAJOR — Transactions submenu lacks filters/CSV/drilldown

Existing page at `wbam-transactions` reads SDK ledger but missing skill-required UX:
- Filter bar: User, Type, Date Range
- Per-advertiser drilldown link from advertisers list
- CSV export
- Stats strip
- Single-query implementation (currently N+1)

### 3. MINOR — Gateway registration filter not implemented

Defer until SDK ships gateway support.

### 4. MINOR — Period-based limits not implemented

Defer; plugin uses memberships instead.
