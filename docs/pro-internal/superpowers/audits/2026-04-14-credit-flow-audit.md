# Credit/Wallet/Balance Flow Audit — WB Ad Manager Pro 1.5.0

**Date:** 2026-04-14
**Branch:** `1.5.0`
**Auditor:** Explore subagent (read-only sweep)

## Executive Summary

The plugin is in a **transitional state** where legacy `advertiser->balance` (float-based money) and SDK-powered `Credits_Bridge::get_balance()` (integer credits) coexist with significant **divergence and duplication**.

`advertiser->balance` is a legacy float field in `wbam_advertisers` table that is NO LONGER the source of truth. The SDK ledger (`{prefix}_credit_ledger`) is now authoritative. However, many code paths still read/write the legacy field, creating a **dual-system problem**.

**Recommendation:** migrate to ledger-only via the `2026-04-14-credit-unification.md` plan.

---

## 1. Balance Read / Display

### 1.1 Legacy direct reads (`$advertiser->balance` as currency amount)

| Location | File:Line | Usage | Issue |
|----------|-----------|-------|-------|
| Admin list table | `includes/Admin/class-advertisers-list-table.php:287` | UI display (balance column) | Shows float; not synced with SDK |
| Admin advertiser view | `includes/Core/class-pro-admin.php:731` | UI display (balance card) | Shows `$advertiser->balance` formatted as price |
| Admin adjust-balance form | `includes/Core/class-pro-admin.php:836-837` | UI display + form data | Displays and prefills adjustment form with stale value |
| Admin adjust-balance warning | `includes/Core/class-pro-admin.php:823` | Gate check | Checks `$advertiser->balance <= 0` for warning |
| Portal advertiser balance | `includes/Modules/Advertisers/class-advertiser-shortcodes.php:1915` | UI display | Shows balance in portal dashboard |
| Portal advertiser balance (formatted) | `includes/Modules/Advertisers/class-advertiser-shortcodes.php:3071` | UI display | `$advertiser->get_formatted_balance()` |
| User profile integration | `includes/Modules/Advertisers/class-user-profile-integration.php:90` | UI display | Shows balance on user profile |
| REST API (advertiser detail) | `includes/Modules/Advertisers/class-advertiser-api.php:402` | REST API output | Returns `balance` field directly |
| REST API (wallet endpoint) | `includes/Modules/Advertisers/class-advertiser-api.php:429-430` | REST API output | Returns both `balance` and `formatted_balance` |
| REST API (transaction list) | `includes/Modules/Advertisers/class-advertiser-api.php:686-687` | REST API output | Returns balance after adjustment |

### 1.2 SDK-powered reads (current source of truth)

| Location | File:Line | Usage |
|----------|-----------|-------|
| Cost banner partial | `templates/portal/partials/cost-balance-banner.php:27` | UI display |
| Wallet tab | `templates/portal/tabs/wallet.php:25` | UI display + ledger |
| Classified shortcode (gate) | `includes/Modules/Classifieds/class-classified-shortcodes.php:2246` | Gate check |
| Advertiser shortcode portal | `includes/Modules/Advertisers/class-advertiser-shortcodes.php:424` | UI display |
| Admin advertiser detail (ledger) | `includes/Core/class-pro-admin.php:656` | Recent transactions |
| Admin transactions list | `includes/Admin/class-transactions-list-table.php:335,399` | Ledger query |
| Email notifications | `includes/Modules/Notifications/class-email-notifications.php:586,637` | Email content |
| Classified cost check (API) | `includes/Modules/Classifieds/class-classified-api.php:808,991,1051` | Gate check |
| Membership manager | `includes/Modules/Memberships/class-membership-manager.php:265,459` | Gate check |

### Mismatch #1: Ad submission balance check

`includes/Modules/AdSubmissions/class-ad-submission-manager.php:132` reads `$advertiser->balance < $required_balance` — should call `Credits_Bridge::get_balance()`.

---

## 2. Balance Mutation

### 2.1 Legacy direct writes (`$advertiser->balance` field assignment)

| Location | File:Line | Type |
|----------|-----------|------|
| Advertiser manager create | `includes/Modules/Advertisers/class-advertiser-manager.php:194` | Field assignment |
| Advertiser manager adjust | `includes/Modules/Advertisers/class-advertiser-manager.php:305-307` | Calls `credit_balance/debit_balance` |
| User profile integration | `includes/Modules/Advertisers/class-user-profile-integration.php:258` | Direct write from POST |
| User profile (new) | `includes/Modules/Advertisers/class-user-profile-integration.php:305` | Sets balance to 0 |
| `Advertiser::credit_balance()` | `includes/Modules/Advertisers/class-advertiser.php:446-453` | Increments + saves |
| `Advertiser::debit_balance()` | `includes/Modules/Advertisers/class-advertiser.php:474-486` | Decrements + saves |

**Critical:** these methods do not write to the SDK ledger.

### 2.2 SDK-powered writes (via `Credits_Bridge`)

| Location | File:Line | Operation | Consumer |
|----------|-----------|-----------|----------|
| Campaign activation | `includes/Modules/Campaigns/class-campaign-manager.php:248` | `hold()` | `campaign_budget` |
| Campaign completion | `includes/Modules/Campaigns/class-campaign-manager.php:365` | `refund()` | unused budget |
| Billing (hourly) | `includes/Modules/Wallet/class-billing-manager.php:217` | `deduct()` | CPM/CPC spend |
| Classified posting | `includes/Modules/Classifieds/class-classified-api.php:850` | `deduct()` | `classified_listing` |
| Ad submission charge | `includes/Modules/AdSubmissions/class-ad-submission-manager.php:226` | `deduct()` | `ad_submission` |
| Ad submission refund | `includes/Modules/AdSubmissions/class-ad-submission-manager.php:579` | `refund()` | rejection refund |
| Membership charge | `includes/Modules/Memberships/class-membership-manager.php:280` | `deduct()` | membership |
| Admin balance (credit) | `includes/Core/class-pro-admin.php:5017` | **`hold()`** | **WRONG — should be `topup()`** |
| Admin balance (debit) | `includes/Core/class-pro-admin.php:5019` | `deduct()` | admin adjustment |

### Mismatch #2 (CRITICAL): Admin "credit" path uses `hold()`

`hold()` writes a negative ledger row reserving credits; doesn't increase balance. Admin "Credit Adjustment" actually doesn't add credits.

### Mismatch #3 (CRITICAL): Adjust-balance writes bypass SDK ledger

`Advertiser_Manager::adjust_balance()` calls `credit_balance/debit_balance` which mutate the legacy field only. SDK ledger never sees these changes.

---

## 3. Transactions & Ledger

### 3.1 Legacy `wbam_transactions` table

- Schema still defined in installer (`class-installer.php:448`)
- Removed from runtime in v1.5.0
- **Revenue dashboard still queries it** (`class-revenue-dashboard.php:699,726`) — **BROKEN**
- `Billing_Manager::process_hourly_billing()` references it (`class-billing-manager.php:319`) — possibly broken

### 3.2 SDK credit ledger

- Table: `{prefix}_credit_ledger` (managed by SDK)
- Read via `Credits_Bridge::get_ledger()` in:
  - `includes/Admin/class-transactions-list-table.php` (admin transactions page) ✓
  - `includes/Core/class-pro-admin.php:656` (advertiser detail) ✓
  - `templates/portal/tabs/wallet.php:27` (portal wallet tab) ✓
  - `includes/Modules/Notifications/class-email-notifications.php:586` (emails) ✓
  - `includes/Modules/Advertisers/class-advertiser-api.php:443` (REST) ✓

---

## 4. Settings / Module Flags

### Wallet module gate

| Location | File:Line | Check |
|----------|-----------|-------|
| Pro plugin init | `includes/Core/class-pro-plugin.php:222` | `is_module_enabled('wallet')` |
| Pro abilities | `includes/Core/class-pro-abilities.php:125` | `is_module_enabled('wallet')` |
| Admin adjust balance | `includes/Core/class-pro-admin.php:804` | `is_module_enabled('wallet')` |
| Portal wallet tab | `templates/portal/tabs/wallet.php:21` | `Credits_Bridge::is_enabled()` |

**Mismatch #4:** Portal uses SDK check; admin uses module flag. They can diverge.

---

## 5. REST API Surface

| Endpoint | File:Line | Returns | Status |
|----------|-----------|---------|--------|
| `GET /advertiser/wallet` | `class-advertiser-api.php:413` | `balance`, `formatted_balance`, `total_spent` | LEGACY |
| `GET /advertiser/stats` | `class-advertiser-api.php:368` | `balance` (float) | LEGACY |
| `GET /advertiser/profile` | `class-advertiser-api.php:345` | `balance` (float) | LEGACY |
| `GET /advertiser/transactions` | `class-advertiser-api.php:443` | SDK ledger entries | SDK ✓ |
| `PUT /advertiser/wallet` | `class-advertiser-api.php:612` | Adjust via admin ability | LEGACY |
| Ability `get_balance` | `class-pro-abilities.php:1775` | `$advertiser->balance` | LEGACY |
| Ability `get_ledger` | `class-pro-abilities.php:1822` | SDK ledger | SDK ✓ |

**Mismatch #5:** `get_balance` ability returns stale legacy field, `get_ledger` returns current SDK data.

---

## 6. Email Notifications

### Wallet credited
- Template: `templates/emails/wallet-credited.php`
- Triggered: `wbam_credits_added` (bridged from SDK `wbcom_credits_topped_up`)
- Data: `Credits_Bridge::get_balance()` ✓

### Low balance
**Mismatch #6:** Two parallel low-balance systems:
1. Legacy: `Advertiser_Email_Notifications` checks `$advertiser->balance > $threshold`
2. New: `Email_Notifications` listens to SDK `wbcom_credits_low` hook

Risk: duplicate notifications or missed alerts depending on which fires first.

---

## 7. Admin UI Surfaces

### `wbam-advertisers` list
- Balance column shows `wbam_format_price( $item->balance )` — **stale legacy field**

### `wbam-advertisers&action=view`
- Balance card shows `$advertiser->balance` — stale
- Recent ledger via `Credits_Bridge::get_ledger()` ✓

### `wbam-advertisers&action=adjust_balance`
- Current balance shows `$advertiser->balance` — stale
- Submit handler calls `Credits_Bridge::hold/deduct` — see Mismatch #2

### `wbam-transactions`
- Reads from SDK ledger ✓ — but missing filters/CSV export per skill
- Page title says "Transactions" — could be confused with retired wallet transactions table

### Revenue dashboard
- Queries `wbam_transactions` table — **BROKEN** (table dropped)

---

## 8. Frontend Portal Surfaces

### Advertiser portal shortcode
- Stat box (line 3071): `$advertiser->get_formatted_balance()` — LEGACY
- Sidebar (line 424): `Credits_Bridge::get_balance()` — SDK ✓
- **Same advertiser, two displayed balances** ($X.XX and Y credits)

### Wallet tab
- All SDK ✓

### Cost-balance banner
- All SDK ✓

---

## Critical Issues Summary

| # | Issue | Severity | File:Line |
|---|-------|----------|-----------|
| 1 | `$advertiser->balance` not synced with SDK | CRITICAL | Multiple |
| 2 | Admin "credit" adjustment uses `hold()` (negative) | CRITICAL | `class-pro-admin.php:5017` |
| 3 | `adjust_balance` bypasses SDK | CRITICAL | `class-advertiser-manager.php:305-307` |
| 4 | Revenue dashboard queries dropped table | CRITICAL | `class-revenue-dashboard.php:699,726` |
| 5 | Ad submission gate reads legacy balance | HIGH | `class-ad-submission-manager.php:132` |
| 6 | Portal shows two balance values | HIGH | `class-advertiser-shortcodes.php` |
| 7 | User profile writes legacy field only | HIGH | `class-user-profile-integration.php:258` |
| 8 | Wallet module gate vs SDK gate mismatch | MEDIUM | `class-pro-admin.php:804` vs `wallet.php:21` |
| 9 | REST `get_balance` returns legacy field | MEDIUM | `class-pro-abilities.php:1775` |
| 10 | Two low-balance notification systems | MEDIUM | `class-email-notifications.php` vs `class-advertiser-email-notifications.php` |
| 11 | Installer still defines removed `wbam_transactions` | MEDIUM | `class-installer.php:448,1342` |

---

## Divergence Matrix

| Surface | Source | Status |
|---------|--------|--------|
| Admin advertisers list | Legacy field | Stale |
| Admin advertiser detail balance | Legacy field | Stale |
| Admin advertiser detail ledger | SDK | Current |
| Admin adjust-balance form display | Legacy field | Stale |
| Admin adjust-balance handler | SDK (with bug — uses hold) | Partial |
| Admin transactions page | SDK ledger | Current |
| Portal sidebar | SDK | Current |
| Portal stat box widget | Legacy field | Stale |
| Portal wallet tab | SDK | Current |
| Portal cost banner | SDK | Current |
| REST `/wallet` | Legacy | Stale |
| REST `/transactions` | SDK | Current |
| Email balance amount | SDK | Current |
| Low balance email trigger | Both | Risk of dup |
