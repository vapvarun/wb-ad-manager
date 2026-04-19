# Membership plans — complete user flow (v1.5.0 finish line)

## Motivation

QA of the 11-step test plan (Basecamp #9688110513) exposed that membership
semantics were silently making choices that confuse users:

- Cancel = instant benefit loss (even though they paid through period).
- Switch = double-charge (full new price on top of already-paid old).
- Renewal failure = undefined (no past_due / grace).
- No reactivate path after cancel.
- Cancellation email missing.

This plan closes the loop so end users never see a confusing state.

## State machine

| Status       | Means                                                                    | Benefits? |
|--------------|--------------------------------------------------------------------------|-----------|
| `active`     | Current period, paid, auto-renews at `renews_at`.                        | Yes       |
| `cancelled`  | User cancelled but `expires_at` not reached. Can reactivate before then. | Yes       |
| `past_due`   | Renewal charge failed, 3-day grace window in `expires_at`.               | Yes       |
| `expired`    | `expires_at` passed. Can re-subscribe (fresh row).                       | No        |
| `switched`   | Terminal. User moved to another plan; this row superseded by newer.      | No        |

## Flows

### Subscribe (no existing effective sub)
1. Charge `plan.price` via `Credits_Bridge::charge()`. (Free plan = skip.)
2. Insert row: `status=active`, `started_at=now`, `renews_at=now+cycle`, `expires_at=null`.
3. Fire `wbam_subscription_created` → email "Welcome to {plan}".

### Switch plan (existing active or cancelled-not-expired sub)
1. Compute **unused refund** on current plan:
   `refund = floor( (renews_at - now) / (renews_at - started_at) * old_price )`.
2. If refund > 0 → `Credits_Bridge::credit()` back to wallet (item_id = old_sub.id).
3. Mark old row `status=switched`, `expires_at=now`.
4. Charge new `plan.price`. Insert new row `status=active`, fresh cycle.
5. Fire `wbam_subscription_switched( $old_sub_id, $new_sub_id, $refund_amount )`.
6. Email: "Switched from {old} to {new}. ${refund} refunded, ${new_price} charged. New renewal {new_renews_at}."

### Cancel (active → cancelled)
1. Set `status=cancelled`, `cancelled_at=now`, `expires_at = renews_at` (keep benefits until period end).
2. Fire `wbam_subscription_cancelled` → email "Your {plan} subscription is cancelled.
   You keep benefits until {expires_at}. No renewal charge. Click Reactivate anytime before then."

### Reactivate (cancelled & not expired → active)
1. Set `status=active`, `cancelled_at=null`, `expires_at=null`. Keep original `renews_at`.
2. Fire `wbam_subscription_reactivated` → email "Welcome back. Your {plan} is active again.
   Next renewal {renews_at} for ${price}."

### Renewal success (daily cron, active with renews_at<=now)
1. `Credits_Bridge::charge( plan.price )`.
2. Bump `renews_at += cycle_days`.
3. Fire `wbam_subscription_renewed` → email receipt.

### Renewal failure (insufficient credits)
1. Set `status=past_due`, `expires_at = now + 3 days`.
2. Fire `wbam_subscription_payment_failed( $sub_id, $required_amount )` →
   email "Your {plan} wallet balance is ${balance} but renewal needs ${price}.
   Top up by {expires_at} or your plan will expire."
3. Benefits retained during grace.
4. Daily cron retries charge on past_due rows. If succeeds → back to active.
5. If grace window expires → status=expired → fire `wbam_subscription_expired`.

### Cancel during past_due
- Same as normal cancel: `expires_at` stays, status flips to cancelled.
- No refund (nothing paid for the failed period).

### Downgrade with listings over new limit
- No forced unpublish. Active listings remain visible.
- `can_post_listing()` returns WP_Error blocking NEW posts until count drops
  under new limit.
- Portal banner: "You have X listings on the {Basic} plan which allows Y.
  Existing listings stay active. Delete some to post new ones, or upgrade."

## Effective-subscription query

Replace `WHERE status = 'active'` with:

```sql
WHERE status IN ('active','cancelled','past_due')
  AND (expires_at IS NULL OR expires_at > NOW())
```

So `get_active_subscription()` (rename-able to `get_effective_subscription()`
if preferred) returns the sub a user currently has benefits from, regardless
of whether it's mid-cancel or mid-grace.

## Method signatures

| Method                                  | New? | Returns                  |
|-----------------------------------------|------|--------------------------|
| `subscribe( $adv_id, $plan_id )`        | edit | `int\|\WP_Error`         |
| `switch_plan( $adv_id, $new_plan_id )`  | NEW  | `int\|\WP_Error`         |
| `cancel_subscription( $sub_id )`        | edit | `bool`                   |
| `reactivate( $sub_id )`                 | NEW  | `bool\|\WP_Error`        |
| `get_active_subscription( $adv_id )`    | edit | `object\|null`           |
| `process_renewals()`                    | edit | `void`                   |
| `handle_failed_renewal( $sub )` internal| NEW  | `void`                   |
| `can_post_listing( $adv_id )`           | no   | `true\|\WP_Error`        |

`subscribe()` behavior change: if the advertiser already has an effective
sub, it calls `switch_plan()` internally. Callers that always want "fresh
subscribe" use the lower-level `insert_subscription_row()` (private).

## Hooks

| Action                                | Args                                | Fired from        |
|---------------------------------------|-------------------------------------|-------------------|
| `wbam_subscription_created`           | `$sub_id, $advertiser_id, $plan`    | subscribe         |
| `wbam_subscription_cancelled`         | `$sub_id`                           | cancel            |
| `wbam_subscription_reactivated`       | `$sub_id, $advertiser_id, $plan`    | reactivate        |
| `wbam_subscription_switched`          | `$old_sub_id, $new_sub_id, $refund` | switch_plan       |
| `wbam_subscription_renewed`           | `$sub_id, $advertiser_id`           | process_renewals  |
| `wbam_subscription_payment_failed`    | `$sub_id, $required_amount`         | process_renewals  |
| `wbam_subscription_expiring`          | `$sub_id, $advertiser_id, $days`    | send warnings     |
| `wbam_subscription_expired`           | `$sub_id, $advertiser_id`           | process_renewals  |

## Portal UI (templates/portal/tabs/membership.php)

- **No active sub** → just plan grid with "Subscribe" buttons.
- **`active`** → current-plan card + plan grid. Plan cards show either
  "Current Plan" (disabled) or "Switch to {plan}" (opens confirm modal
  with proration preview).
- **`cancelled` (not yet expired)** → yellow banner:
  "Cancelled. Benefits until {expires_at}. [Reactivate]" + grid.
  Cancel button hidden (already cancelled).
- **`past_due`** → red banner:
  "Payment failed. Top up ${diff} within {remaining_days} days or your
  plan expires on {expires_at}. [Top up wallet]" + grid.

Switch confirm modal (JS): shows
- Prorated refund amount from current plan
- Charge amount for new plan
- New renewal date
- [Confirm] [Cancel]

## Admin UI

Plans CRUD list table already exists at `wbam-membership-plans`. No changes
needed for v1.5.0. Admin force-cancel is deferred to v1.6.0.

## Woo/PMPro-direct billing

**Deferred to v1.6.0.** v1.5.0 documents that membership plans are
credit-wallet-billed only. A later version can add a
`Subscription_Billing_Adapter` interface + per-plan setting to route
recurring charges through Woo Subscriptions / PMPro recurring.

## Out of scope for v1.5.0

- Plan switching scheduled-for-period-end (alternative to immediate+prorate).
- Admin force-cancel with refund.
- Tiered downgrade workflow (e.g. auto-select which listings to keep).
- Woo Subscriptions / PMPro recurring integration.
- Pause/resume without cancel.

## Verification checklist (end-to-end, after build)

- [ ] Fresh subscribe to paid plan → credits charged, sub active, welcome email.
- [ ] Switch Basic→Pro → refund to wallet, Pro charged, old sub `switched`, switch email with both amounts.
- [ ] Cancel active sub → `expires_at=renews_at`, benefits still work, cancel email with correct expires_at date.
- [ ] Reactivate cancelled-not-expired → status=active, reactivate email.
- [ ] Re-subscribe after expiry → fresh row, welcome email.
- [ ] Renewal with insufficient balance → status=past_due, 3-day grace, payment-failed email.
- [ ] During past_due grace → still can post within limit.
- [ ] past_due expires → status=expired, expired email.
- [ ] Downgrade Pro→Basic with 15 listings (Basic limit 10) → switch ok, banner shows, `can_post_listing` blocks new posts.
- [ ] Wallet disabled (wbcom_credits_enabled filter false) → subscribe to paid plan rejected with credits_disabled.
- [ ] Free plan subscribe with wallet disabled → allowed.
- [ ] All 4 new emails render correctly with proration / expires_at values substituted.
- [ ] Local gates green: php lint + phpstan L7 + plugin-check + WPCS.
