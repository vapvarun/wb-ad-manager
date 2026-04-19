# WB Ad Manager Pro — Product Stabilization Plan

## Current State (Updated Feb 2026)

Major progress made in v1.3.1 branch. Remaining items for v1.4.0:
- **No test coverage** — every change risks silent regressions
- **Free ↔ Pro boundary** relies on class_exists() checks, no formal contract

### Completed in v1.3.1
- Ad submission unified through `Ad_Submission_Manager` + `Campaign_Manager::create_from_package()`
- Error handling standardized (Campaign_Manager → WP_Error, Ad_Submission_Manager → WP_Error)
- Budget reservation system for CPM/CPC campaigns
- Money flow protections: idempotency keys, double-credit/debit prevention
- Data flow audit: 6 critical + 4 high + medium/low severity issues fixed
- Manual/bank transfer payment flow with admin approve/cancel
- Admin transactions table with Pending Approval view

## Goal

Ship a stable v1.4.0 where every user-facing flow is verified, consistent, and tested.

---

## Phase 1: Unify Submission Flows (3-4 days) — COMPLETED in v1.3.1

**Status:** Done (commits 52f129a, ed5c497)

- `Ad_Submission_Manager::submit_ad()` now calls `Campaign_Manager::create_from_package()`
- Frontend shortcodes route through Campaign_Manager
- Budget reservation system added for CPM/CPC campaigns
- Flat-rate charged via `charge_for_package()`, CPM/CPC via pre-funded reservation

---

## Phase 2: Standardize Error Handling (2 days) — MOSTLY COMPLETED in v1.3.1

**Status:** Campaign_Manager and Ad_Submission_Manager migrated (commits d30ae46, 3b59d5a, 68e59d4).

**Done:**
- `Campaign_Manager`: `create()`, `update()` → `Campaign|WP_Error`; status methods → `true|WP_Error`
- `Ad_Submission_Manager`: `submit_ad()`, `resubmit()` → `object|WP_Error`
- All callers in Campaign_API, Pro_Admin, Advertiser_Shortcodes updated to `is_wp_error()` checks

**Remaining:**
- `Wallet_Manager`: still returns `Transaction|false` (not yet migrated to WP_Error)

---

## Phase 3: Module Dependency Enforcement (1 day) — PARTIALLY DONE

**Status:** Module dependency enforcement added in commit 5a65d16. Settings_Helper validates dependencies on save.

**Remaining:** UI could show clearer dependency info in module settings.

---

## Phase 4: Functional Test Suite (3-4 days)

**Problem:** No automated tests. QA agents find issues by code scanning, not by running flows.

**Approach:** Write PHPUnit integration tests that run against a real WordPress test database.

### Test Cases by Flow:

#### A. Wallet Operations
```
TEST: Advertiser adds funds via Stripe
  → Pending credit created → Payment confirmed → Balance increases → Transaction recorded

TEST: Admin adjusts balance (credit)
  → Balance increases → Transaction type='adjustment' → Audit logged

TEST: Admin adjusts balance (debit)
  → Balance decreases → Cannot go below 0 unless force → Audit logged

TEST: Concurrent balance updates (race condition)
  → Two debits at same time → Only one succeeds if insufficient → FOR UPDATE prevents corruption
```

#### B. Campaign Lifecycle (per pricing model)
```
TEST: Flat-rate package → Ad submission → Campaign created + wallet charged
  → Package.price debited → Campaign active → No reservation

TEST: CPM package (budget $50) → Ad submission → Budget reserved
  → $50 debited as 'campaign_reserve' → Campaign active → Balance decreased

TEST: CPM package (unlimited) → Ad submission → No upfront charge
  → $0 debited → Campaign active → Hourly billing kicks in

TEST: Campaign pause → Balance unchanged → Resume → No re-charge

TEST: Campaign complete → Refund unused budget
  → (budget - spent) credited as 'campaign_refund'

TEST: Campaign delete → Refund unused → Record deleted
  → Refund fails → Delete blocked (not silent)

TEST: Budget change on active campaign → Adjustment transaction
  → Increase: additional debit → Decrease: partial credit
```

#### C. Ad Submission (both paths produce same result)
```
TEST: Frontend portal submits ad with flat-rate package
  → Same as API submission: campaign created, wallet charged, ad published

TEST: Ad submission fails (insufficient balance)
  → No orphaned campaign, no orphaned post, clear error message

TEST: Admin approves pending ad → Campaign activates → Budget reserved
  → Activation fails → Error shown, ad stays pending

TEST: Admin rejects ad → Refund if flat-rate → Campaign cancelled
```

#### D. Hourly Billing (Cron)
```
TEST: Unlimited CPM campaign accumulates spent → Hourly billing charges unbilled
  → Transaction type='campaign' → billed_amount updated

TEST: Insufficient balance during billing → Campaign auto-paused
  → Audit logged → Advertiser notified

TEST: Idempotency → Same billing hour runs twice → Only charged once
```

#### E. Classifieds
```
TEST: Submit classified with featured upgrade → Wallet charged → Listed as featured

TEST: Admin rejects featured classified → Auto-refund → Balance restored

TEST: Classified expires → Downgraded to standard → Upgrades expired

TEST: File upload → MIME type validated server-side (not client-supplied)
```

#### F. Admin Operations
```
TEST: Admin actions require manage_options capability

TEST: Screen options save for all 7 list tables

TEST: Nonce verification on all form submissions
```

**Implementation:** Use WP's built-in test framework (`wp-phpunit`). Tests go in `tests/` directory.

---

## Phase 5: Free ↔ Pro Integration Hardening (1-2 days)

**Problem:** Pro assumes Free plugin classes exist. If Free updates and renames/removes a class, Pro fatals.

**Fix:**
- Add version compatibility check: `WBAM_VERSION >= '2.5.0'`
- Wrap all Free plugin class references in `class_exists()` or `function_exists()` guards
- Add `wbam_pro_compatible_free_versions` filter for version range enforcement

**Files to check:**
- Every `use WBAM\...` statement in Pro plugin
- Every `\WBAM\Class_Name::method()` call
- Every `apply_filters('wbam_...')` / `do_action('wbam_...')` hook usage

---

## Phase 6: Code Quality Pass (1-2 days)

After all functional fixes are in:
- Run WPCS on all changed files
- Run PHPStan level 5 on core modules (Wallet, Campaigns, Submissions)
- Remove dead code identified in previous audits
- Verify all DB queries use `$wpdb->prepare()`

---

## Execution Order

| Phase | Priority | Status | Dependency |
|-------|----------|--------|------------|
| 1. Unify Submissions | Critical | **DONE** (v1.3.1) | None |
| 2. Error Handling | Critical | **MOSTLY DONE** (Wallet_Manager remaining) | Phase 1 |
| 3. Module Dependencies | High | **PARTIALLY DONE** | None |
| 4. Test Suite | High | Not started | Phase 1+2 |
| 5. Free ↔ Pro Hardening | Medium | Not started | None |
| 6. Code Quality | Medium | Not started | All above |

**Remaining for v1.4.0: ~6-8 days** (Phases 4, 5, 6 + finish 2 and 3)

---

## What Changes for Development Going Forward

1. **Every new feature gets a test case BEFORE code is written**
2. **All ad creation must go through Ad_Submission_Manager** — no raw Campaign_Manager calls from UI code
3. **All wallet operations return WP_Error on failure** — callers must check
4. **Module enable/disable validates dependency chain**
5. **QA runs test suite, not code scanning** — code scanning catches style issues, tests catch logic bugs
