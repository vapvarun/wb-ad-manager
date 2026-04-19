# Credit Unification Plan — WB Ad Manager Pro 1.5.0

**Date:** 2026-04-14
**Branch:** `1.5.0`
**Goal:** make WB Ad Manager Pro credit-only and 100% production ready — no more piecemeal patches.

References:
- `docs/superpowers/audits/2026-04-14-credit-flow-audit.md`
- `docs/superpowers/audits/2026-04-14-sdk-compliance-audit.md`
- Skill: `wbcom-credits-sdk-integration`

---

## 0. Current-State Summary

**The critical production bug.** `includes/Core/class-pro-admin.php:5017` calls `Credits_Bridge::hold()` for the admin "credit" adjustment path. `hold()` writes a **negative** ledger row, so "adding credits" via admin actually reduces the balance.

**Dual storage of truth.** Two independent balance stores live in parallel:
- `wbam_advertisers.balance DECIMAL(10,2)` — written by `Advertiser::save()`, `credit_balance()`, `debit_balance()`, `Advertiser_Manager::adjust_balance()`, `user-profile-integration.php:258,305`, etc.
- `wp_wbam_credit_ledger` (SDK) — written by `Credits_Bridge::{hold,deduct,refund}` and SDK `Credits::*`.

**Display pulls from both** — see flow audit divergence matrix.

**Transactions surface split.** `wbam-transactions` page reads SDK ledger ✓ but lacks filters/CSV/drilldown. Legacy `wbam_transactions` table still defined in installer; revenue dashboard queries it (broken).

**Credits_Bridge gaps.** Wrappers present: `get_balance, hold, deduct, refund, get_ledger, get_cost, get_cost_preview, get_purchase_url, is_enabled`. Missing wrappers: **`topup`**, **`adjust`**, **`cancel_hold`** (SDK supports all three).

**Installer DB_VERSION** currently `3.2.0`. Bump to `3.3.0`.

---

## 1. Target Architecture

- **Single balance store:** SDK ledger. `COALESCE(SUM(amount), 0)` is the balance.
- **Single write path:** all credit mutations go through `Credits_Bridge` → SDK `Credits::*`. No direct writes to `wbam_advertisers.balance`.
- **Legacy field retirement:** `wbam_advertisers.balance` deprecated this release, dropped in 1.6.0. `total_spent` kept (lifetime counter, distinct semantics).
- **Single transactions surface:** `wbam-transactions` becomes the SDK ledger viewer with filters, CSV export, per-advertiser drilldown.
- **Labels:** UI strings continue to say "Wallet" (user-facing) but resolve to SDK ledger underneath.

---

## 2. Phase A — Bridge API Completion

**File:** `includes/Core/class-credits-bridge.php`

Add three wrappers after `refund()`:

```php
public static function topup( $advertiser_id, $amount, $note = '' )
public static function adjust( $advertiser_id, $amount, $note = '' )   // signed
public static function cancel_hold( $advertiser_id, $item_id )
```

Each resolves user_id via `self::get_user_id()` and delegates to `Credits::topup / adjust / cancel_hold`.

Plus: `query_ledger( array $args )` raw-query helper for the new transactions page (avoids N+1 `get_ledger` per advertiser). Filters: user_id, entry_type, date_from/to, item_id, search, orderby, limit, offset.

**Δ:** +110 lines, 1 file.

---

## 3. Phase B — Admin "Adjust Balance" Fix (CRITICAL)

**File:** `includes/Core/class-pro-admin.php`

### B1: Fix the reversed-sign bug (lines 5004–5046)

Replace the hold/deduct branch with signed `adjust()`:
```php
$signed = ( 'credit' === $type ) ? $abs_amount : -$abs_amount;
$result = Credits_Bridge::adjust( $advertiser_id, $signed, $note );
```

Why `adjust` not `topup`/`deduct`: `adjust` is the SDK's "topup or deduct without hold lifecycle" primitive (`Credits.php:267`).

### B2: Make form display use SDK balance (lines 823, 836, 837)

Swap `$advertiser->balance` reads → `Credits_Bridge::get_balance( $advertiser_id )`. Update `data-balance` attribute used by client-side JS.

### B3: Advertiser view balance (lines 730–731)

Swap to `number_format_i18n( Credits_Bridge::get_balance(...) )` with credit suffix.

### B4: Create-advertiser handler (line 4986)

If admin sets `initial_balance > 0`: create advertiser with balance=0, then `Credits_Bridge::topup()` for the initial amount.

**Δ:** +40/-30 lines, 1 file.

---

## 4. Phase C — Legacy Balance Retirement (write side)

Every internal write to `$advertiser->balance` routed through `Credits_Bridge` or removed.

| File:line | Current | Action |
|---|---|---|
| `class-advertiser.php:54` | Public `$balance` | Mark `@deprecated 1.5.0`, keep as read-only mirror |
| `class-advertiser.php:210,234,879` | `save()`/`to_array()` writes balance | `save()` stops writing column; `to_array()` returns live SDK balance |
| `class-advertiser.php:446 credit_balance()` | direct field write | Rewrite to `Credits_Bridge::topup()`; preserve `wbam_advertiser_balance_credited` action |
| `class-advertiser.php:474 debit_balance()` | direct field write | Rewrite to `Credits_Bridge::adjust(-amount)`; preserve action |
| `class-advertiser-manager.php:194` | `create(['balance' => …])` | Ignore `balance` key; admin path tops up explicitly |
| `class-advertiser-manager.php:295 adjust_balance()` | calls credit_/debit_balance | Rewrite to `Credits_Bridge::adjust()`; keep audit log |
| `user-profile-integration.php:258` | direct write from POST | Compute delta vs SDK balance, call `adjust(delta)`. Capability-gated. |
| `user-profile-integration.php:305` | sets balance=0 on new | No-op (default) |
| `class-installer.php:585-718 upgrade_to_2_6_0()` | historical migration | Add comment; no-op on already-migrated DBs; not executed in fresh 3.3.0 |
| `ad-submission-manager.php:132` | reads `$advertiser->balance` | Swap to `Credits_Bridge::get_balance()` |
| `pro-abilities.php:1311` | `$advertiser->wallet_balance` | Verify property exists; swap to SDK |

**Keep:** `total_spent` (lifetime counter, distinct semantics).

**Δ:** +90/-130, ~7 files.

---

## 5. Phase D — Display Unification (read side)

Every UI surface reads from `Credits_Bridge::get_balance()`.

| File:line | Current | Fix |
|---|---|---|
| `class-pro-admin.php:731` | `$advertiser->balance` | covered by Phase B |
| `class-pro-admin.php:823,836,837` | adjust form display | covered by Phase B |
| `class-advertisers-list-table.php:287` | `$item->balance` | `number_format_i18n(Credits_Bridge::get_balance($item->id))` + "credits" suffix |
| `class-advertisers-list-table.php:60,74` | column "Balance", sortable | Rename to "Credits"; remove sort (computed SUM, defer SQL-join sort) |
| `class-advertiser-api.php:402,429,686,1790,1868,1923` | float balance | Return `(int) Credits_Bridge::get_balance(...)`; add `credits` alias |
| `class-advertiser-api.php:636-640 update` | accepts absolute balance | Compute delta server-side, call `adjust()` |
| `class-advertiser-shortcodes.php:793,794,1915,787,788` | float displays | Swap to SDK; format with credit suffix |
| `class-user-profile-integration.php:90,146` | profile widget | Swap to SDK |
| `class-pro-abilities.php:1311` | `wallet_balance` | Swap to SDK |
| `templates/portal/tabs/wallet.php` | already SDK | verify |
| `templates/portal/partials/cost-balance-banner.php` | already SDK | verify |

**New helper** (`includes/Core/functions.php`):
```php
function wbam_get_advertiser_credit_balance( $advertiser_id_or_object ): int
```
Templates use the helper instead of calling Credits_Bridge directly. Auditing becomes `grep ->balance` then convert.

**Δ:** +60/-70, ~10 files.

---

## 6. Phase E — Transactions Page Rewrite

**File:** `includes/Admin/class-transactions-list-table.php` + new admin_init handler.

### Issues
1. N+1: `get_all_ledger_entries()` iterates advertisers calling `get_ledger`. Replace with `Credits_Bridge::query_ledger()` from Phase A.
2. Views (entry_type filter) list `credit, debit, hold, release, refund` but SDK emits `topup, deduction, hold, refund`. Reconcile.
3. Missing: date range filter, user free-text search, CSV export, per-advertiser drilldown.
4. Missing: stats strip (Credits Issued / Used / Active Users).

### Tasks
- Add filter UI in `extra_tablenav`: from/to date, user search, type select.
- New CSV export handler on `admin_init` in `Pro_Admin`: nonce-verified, streams unpaginated query result. Columns: `id, user_id, user_email, advertiser_company, entry_type, amount, item_id, note, created_at`.
- Add row action "View ledger" on `class-advertisers-list-table.php` near line 187 → `wbam-transactions?advertiser_id={id}` (page already honors this query var).
- Stats card row above table: aggregate counts/sums across the current filter scope.

**Δ:** +200/-80, 2 files.

---

## 7. Phase F — Data Migration (DB_VERSION 3.3.0)

**File:** `includes/Core/class-installer.php`

- Bump `DB_VERSION` to `3.3.0` (line 26)
- Add `upgrade_to_3_3_0()`, idempotent

### Algorithm
```
For each row in {prefix}wbam_advertisers WHERE balance > 0:
  user_id = row.user_id
  if !user_id: continue
  existing = SUM(amount) FROM ledger WHERE user_id = user_id AND note = 'Migration seed v3.3.0'
  if existing > 0: continue   // idempotent
  insert ledger: entry_type='topup', amount=INT(round(balance)), item_id=0, note='Migration seed v3.3.0'

UPDATE wbam_advertisers SET balance = 0 WHERE id IN (migrated)
Audit_Logger entry per advertiser
```

### Optional (flag-gated) back-fill from `wbam_transactions`
Off by default. Admin notice offers one-time import via cron `wbam_credits_backfill_transactions`. Maps `type` → SDK `entry_type`. Skip non-'completed' rows.

### Rounding
DECIMAL → INT via `round()`. Admin notice discloses cent-level precision loss; most sites use whole-credit pricing already.

### Rollback
Forward-only. Mitigations:
1. Snapshot table `wbam_advertisers_balance_backup_20260414` (id, user_id, balance).
2. `wp wbam credits:rollback-3-3-0` WP-CLI command (out of scope, but data preserved).
3. Idempotent re-run safe.

**Δ:** +180 lines, 1 file.

---

## 8. Phase G — Verification Matrix

| # | What to verify | Test |
|---|---|---|
| 1 | Balance is SDK-sourced everywhere | Topup advertiser 500 via Bridge. Assert: admin list, view, profile widget, portal sidebar, REST `/advertisers/{id}` all return 500. |
| 2 | Admin topup | Adjust → Credit 100 → ledger has `topup +100`, balance 600, audit entry, low-balance email NOT fired |
| 3 | Admin debit | Adjust → Debit 50 → ledger `deduction -50`, balance 550 |
| 4 | Hold lifecycle | Activate CPM campaign budget 100: ledger `hold -100`, balance 450. Complete: hold→deduction. Cancel: refund. |
| 5 | Classified/ad submission | Submit ad with $10 package: deduct -10. Reject: refund +10. |
| 6 | Transactions page | Page lists 6 ledger rows. Filter by entry_type=topup → 2 rows. Filter by advertiser → only that advertiser. CSV export valid. Drilldown from advertiser view pre-filters. |
| 7 | Migration idempotency | Pre-3.3.0 DB with balance=1000: upgrade → ledger has seed +1000, advertisers.balance=0. Re-run: no duplicate seed (idempotent). |

### Automated
- `php -l` on every modified file
- `mcp__wpcs__wpcs_check_file` clean
- PHPUnit:
  - `CreditsBridgeTest::test_topup_adjust_cancel_hold_wrappers`
  - `AdjustBalanceAdminTest::test_credit_path_writes_positive_ledger_row`
  - `AdjustBalanceAdminTest::test_debit_path_writes_negative_ledger_row`
  - `MigrationTest::test_upgrade_to_3_3_0_seeds_ledger_and_is_idempotent`
  - `TransactionsListTableTest::test_csv_export_and_filter_by_advertiser`

### Manual Playwright checklist
- Admin advertisers list: Credits column shows live SDK balance
- Adjust Balance form: current balance matches SDK; submit Credit 25, debit 10, deltas correct
- Transactions page: rows with correct entry_type badges
- CSV export: clicks button, downloads, row count matches filter
- Advertiser row action "View Ledger" opens transactions page pre-filtered
- Portal `/dashboard?tab=wallet`: balance identical to admin
- Portal campaign create: insufficient-balance reads live SDK
- WP user profile widget shows SDK balance; admin edits apply via `adjust`

---

## 9. Execution Sequencing

Recommended merge order (each = one Basecamp card):
1. **Phase A** (Bridge wrappers) — pure additive, zero risk
2. **Phase B** (admin Adjust Balance fix) — CRITICAL BUG FIX, ship first
3. **Phase D read-side** (readers → SDK) — low risk
4. **Phase F** (migration 3.3.0) — ship together with or just before Phase C
5. **Phase C write-side** (retirement) — requires Phase F to seed
6. **Phase E** (transactions page polish + CSV + drilldown) — last
7. **Phase G** — verification gate before release tag

**Total:** ~14 files modified, 0 new files. Net Δ: +680/-310 (net +370 lines).

---

## 9.5 — Phase H: Admin Pricing Control (Ads + Classifieds)

The Pro plugin sells two product types and admins must control the credit price for each, with no currency/credit confusion.

### Pricing surfaces inventory

| Surface | Stored at | File | Currently displayed as |
|---|---|---|---|
| Ad package flat price | `Package->price` (DECIMAL) | admin form `class-pro-admin.php:4574` | currency `wbam_format_price()` |
| Ad package per-unit (CPM/CPC) | `Package->price_per_unit` (DECIMAL) | `:4583` | currency |
| Campaign budget (admin/user) | `Campaign->budget` (DECIMAL) | campaign forms | currency |
| Classified base listing cost | `_wbam_listing_cost` post meta | classified submission | currency |
| Classified `featured` upgrade | `wbam_pro_pricing_settings.featured_price` option | `Pricing_Calculator:42` | currency |
| Classified `highlighted` upgrade | `..._highlighted_price` | `:43` | currency |
| Classified `urgent` upgrade | `..._urgent_price` | `:44` | currency |
| Classified `bump` upgrade | `..._bump_price` | `:45` | currency |
| Classified `top` upgrade | `..._top_price` | `:46` | currency |

All five extras are per-action **flat fees** in the pricing settings option — already perfect for credit pricing, just need re-labelling and an admin UI page.

### H1: Re-label all pricing inputs as "credits" (display only)

- Replace every `wbam_format_price()` on package/upgrade/budget admin forms with `number_format_i18n() + ' ' + _x('credits', 'unit suffix')`.
- Inputs change from `step="0.01"` (cents) to `step="1"` (whole credits) where appropriate. Keep DECIMAL storage for backwards-compat (Phase F migration won't touch existing values).
- Field hints clarify: "How many credits the user spends to use this".

### H2: Single Pricing admin page

Already exists as `wbam_pro_pricing_settings` option — surface it in admin under **WB Ad Manager → Settings → Pricing** tab (verify the tab exists; if not, add it). One screen lists every credit price in one table:

| Item | Credits | |
|---|---|---|
| Featured listing | [ 5 ] | save |
| Highlighted listing | [ 3 ] | save |
| Urgent badge | [ 4 ] | save |
| Bump to top | [ 2 ] | save |
| Top of page | [ 10 ] | save |

Plus a separate sub-section: "Ad Packages" linking to the Packages CPT list (admins set per-package prices on the package edit screen).

Same screen also shows the **Credit Mappings** summary (or links to it) so admins see the full money-flow on one page: "$10 WC product → 100 credits → user spends 5 credits per featured listing".

### H3: User-side cost preview parity

Wherever the user sees a price on the frontend, it shows as "N credits" (no $ sign). Already mostly done via the cost-balance-banner partial; verify:

- Ad submission package picker shows package credit cost
- Classified extras checkboxes show "+5 credits", "+3 credits", etc.
- Review step shows "Total: 12 credits" (no currency)
- Submit button confirms "Submit and spend 12 credits"

### H4: Admin guard rails

- Validation: package price ≥ 0, integers only when input is integer
- Warning if a package costs more credits than typical user balance (configurable threshold)
- Per-extra preview: "When a user selects 'Featured', they will be charged 5 credits"

### H5: Documentation note

Add an admin notice on first install (post-migration): "WB Ad Manager Pro now uses a unified credit system. All prices are denominated in credits. Configure your prices in Settings → Pricing and your credit-source mappings in Settings → Credits."

**Δ:** ~50/-20 lines, 3 files (`class-pro-admin.php`, `class-pricing-calculator.php`, `templates/portal/*`).

**Sequencing:** ship after Phase D so the unified currency story is complete.

---

## 10. Open Questions

1. **Fractional credits?** Legacy DECIMAL(10,2) → INT. OK to round? Plan assumes yes; admin notice discloses.
2. **Sortability of Credits column** — drop sort or implement SQL-joined sort? Plan defers.
3. **`wbam_transactions` back-fill** — default OFF per plan. Confirm.
4. **Legacy `balance` column drop** — proposed for 1.6.0. Confirm release cadence.
5. **`$advertiser->wallet_balance` at `pro-abilities.php:1311`** — property doesn't exist on Advertiser class; verify if dormant code path or live bug.
