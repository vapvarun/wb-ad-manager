---
journey: advertiser-sees-where-credits-went
plugin: wb-ad-manager-pro
priority: high
roles: [advertiser]
covers: [spend-by-campaign-transparency]
prerequisites:
  - "Pro active; credits module enabled"
  - "A funded advertiser with at least one campaign that has spent"
estimated_runtime_minutes: 3
---

# An advertiser can reconcile a falling balance without asking anyone

Credit History answers "what moved and when". On its own it cannot answer "on
what", because a ledger line is a date and an amount. An advertiser watching
their balance drop had no way to tie it to their own reporting, so every
reconciliation question became an email to the site owner.

## Setup

- Site: `$SITE_URL`
- Advertiser: `clio`, funded, owning a campaign with non-zero `spent`

## Steps

### 1. Open the wallet
- **Action**: `playwright_navigate $SITE_URL/advertiser-dashboard/?autologin=clio&tab=wallet`
- **Expect**: `.wbam-campaign-spend` section present, between the balance and
  Credit History.

### 2. Check the figures reconcile
- **Expect**: one row per campaign that has spent, showing Campaign, Status,
  Budget, Spent, Remaining. `Budget - Spent = Remaining` for each budgeted row.
- **Cross-check**: `SELECT name, budget, spent FROM wp_wbam_campaigns WHERE advertiser_id = <A>;`
  The table must agree with the rows.

### 3. Unlimited-budget campaigns
- **Expect**: budget shows `Unlimited` and Remaining shows a dash. There is
  nothing to count down from, so a number there would be invented.

### 4. Total
- **Expect**: the `Total spent` figure comes from the SUM aggregate over ALL the
  advertiser's campaigns, so it must still be correct for an advertiser with
  more than 50 campaigns (the page size of the row query).

### 5. Slot attribution is stated, not implied
- **Expect**: the note below the table says slot-level breakdown is not included.
  Silence here would let an advertiser assume the table already breaks it down.

### 6. Mobile
- **Action**: resize to 390px
- **Expect**: the table scrolls inside its own container; the portal itself does
  not scroll sideways.

## Pass criteria

- Figures reconcile with `wp_wbam_campaigns`.
- No horizontal page scroll at 390px.
- Unlimited-budget rows show no invented "remaining" figure.
