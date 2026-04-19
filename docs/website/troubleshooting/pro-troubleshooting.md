# Pro Troubleshooting

> **PRO feature.** Requires the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on on top of the free plugin.

Common issues and solutions for WB Ad Manager Pro.

---

## Payment Issues

### Stripe Payment Fails

**Symptoms:** Advertiser sees a payment error; wallet is not credited.

**Checklist:**
1. Confirm you are using the correct key set — test keys (`pk_test_`, `sk_test_`) in test mode and live keys (`pk_live_`, `sk_live_`) in live mode.
2. Verify SSL is active — Stripe requires HTTPS.
3. Check the webhook is registered in the Stripe Dashboard at: `https://yoursite.com/wp-json/wbam-pro/v1/stripe/webhook`
4. Confirm the webhook secret (`whsec_...`) in **Pro Settings → Payments** matches the one in Stripe Dashboard.
5. Required webhook events: `payment_intent.succeeded`, `checkout.session.completed`, `charge.refunded`.
6. Check Stripe Dashboard → Developers → Logs for failed delivery attempts.
7. Enable `WP_DEBUG_LOG` and check `wp-content/debug.log` for PHP errors on webhook receipt.

### Wallet Not Updated After Stripe Payment

The wallet is credited by the webhook, not by the redirect. If the webhook is not delivered, the wallet remains at the pre-payment balance.

1. Confirm the webhook URL is reachable from the internet (not a localhost install).
2. Check Stripe Dashboard for failed webhook deliveries and retry them manually.
3. Confirm the webhook signing secret is correct — a mismatched secret causes signature validation to fail silently.

### Manual/Bank Transfer Payment Stuck in Pending

**How the offline payment flow works:**

1. Advertiser selects "Bank Transfer" and submits a fund request.
2. A transaction record is created with status `pending` and type `payment`.
3. The admin receives a notification email with the advertiser's bank reference.
4. The admin reviews the request in **WB Ads → Transactions**.
5. Admin clicks **Approve** (row action) — the wallet is credited and the transaction moves to `completed`.
6. Admin clicks **Cancel** (row action) — the request is rejected and the transaction moves to `cancelled`.

**If the payment stays in pending:**
- Confirm the admin has the `manage_options` capability.
- Confirm the nonce in the Transactions admin page has not expired (reload the page if it has been open for more than 12 hours).
- Check `wp-content/debug.log` for PHP errors during the approve/cancel action.
- Verify the `wbam_transactions` table exists: run `SHOW TABLES LIKE 'wp_wbam_transactions';` in your database tool.

---

## Wallet Balance Mismatch

**Symptoms:** Advertiser reports a balance that does not match their expected amount.

**Diagnostic steps:**

1. Go to **WB Ads → Transactions** and filter by the advertiser's ID.
2. Check for any duplicate transactions — the wallet uses `idempotency_key` constraints to prevent double-credits, but this only applies to operations that set the key.
3. Confirm that `campaign_reserve` transactions are not being treated as available balance. Reserved funds are shown in the advertiser's record but are not spendable.
4. Check for `campaign_refund` transactions — these credit the wallet when a campaign completes or is cancelled and represent returned reserved funds.
5. If a CPM/CPC campaign was active, check for `campaign_adjust` transactions — these represent the difference between the reserved amount and actual usage.

---

## Budget Reservation Not Triggering

**Symptoms:** A CPM or CPC campaign activates but no `campaign_reserve` transaction appears in the wallet.

**Cause:** Reservation only fires when `Campaign::update_status()` transitions the campaign to `active`. The `Campaign_Manager::create()` method does **not** trigger a reservation.

**Checklist:**
1. Confirm the campaign's `pricing_model` is `cpm` or `cpc` — flat-rate campaigns do not reserve funds.
2. Confirm the campaign `budget` field is greater than `0`.
3. Confirm the advertiser has sufficient wallet balance. If balance is insufficient, the status transition fails silently and the campaign remains in its previous state.
4. Check the `wbam_campaigns` table: the `status` column should read `active` after a successful transition.
5. Check `wp-content/debug.log` for `WP_Error` responses from the Campaign Manager during status changes.

---

## Campaign Status Transitions Failing

Valid status transitions are enforced by `Campaign_Manager::is_valid_transition()`. Invalid transitions are rejected.

**Allowed transitions:**

| From | To |
|------|----|
| `draft` | `pending`, `active`, `cancelled` |
| `pending` | `active`, `cancelled` |
| `active` | `paused`, `completed`, `expired`, `cancelled` |
| `paused` | `active`, `completed`, `cancelled` |

**Symptoms:** Admin clicks "Activate" but the status does not change.

1. Check the current campaign status in the database. If it is `completed` or `expired`, it cannot be reactivated.
2. Confirm the advertiser wallet has enough balance to cover the budget reservation (for CPM/CPC campaigns).
3. Check `wp-content/debug.log` for `WP_Error` objects returned by `Campaign_Manager::update_status()`.
4. Never check the return value with `! $result` — `WP_Error` is truthy in PHP. Always use `is_wp_error($result)`.

---

## Module Dependency Errors

**Symptoms:** A module appears disabled even though you enabled it in **Pro Settings → Modules**.

**Cause:** Modules have dependency requirements. A module is effectively disabled if any of its dependencies are disabled.

**Dependency map:**
- Campaigns requires **Wallet**
- Packages requires **Wallet** and **Campaigns**
- Ad Submissions requires **Wallet**, **Campaigns**, and **Packages**
- Classifieds requires **Wallet**

**Fix:** Enable all required dependency modules first, then save. If enabling dependencies causes the expected module to still appear inactive, check for PHP errors — a fatal error during module boot can prevent registration.

---

## Classified Meta Migration Issues

Version 1.4.0 (DB 2.8.0) introduced the `wbam_classified_meta` table to replace `_wbam_custom_fields` post meta. If you upgraded from a version before 2.8.0, custom field data may not have migrated correctly.

**Symptoms:** Custom fields are missing from classified listings after upgrade.

**Checklist:**
1. Confirm the `wbam_classified_meta` table exists:
   ```sql
   SHOW TABLES LIKE 'wp_wbam_classified_meta';
   ```
2. Check if the old post meta still exists:
   ```sql
   SELECT COUNT(*) FROM wp_postmeta WHERE meta_key = '_wbam_custom_fields';
   ```
   If this returns rows but `wbam_classified_meta` is empty, the migration did not run.
3. Trigger the migration by forcing the plugin's upgrade routine to re-run: deactivate WB Ad Manager Pro, then reactivate it. The installer runs `maybe_upgrade()` on activation and will create missing tables and migrate legacy meta if the stored DB version is behind the current `DB_VERSION` constant. If reactivation does not fix the issue, contact support with your current DB version (stored in the `wbam_pro_db_version` option).
4. Confirm the classified post delete hook is working — meta rows should auto-delete when a classified is deleted (via `Classified::delete()` or `cascade_delete_classified_data()`).

---

## Advertiser Portal Issues

### Dashboard Shows Blank Page

1. Confirm the user is logged in.
2. Confirm the user has an advertiser account linked. Non-advertiser users see a "become advertiser" registration prompt, not a blank page.
3. If the page appears empty after login, check for a PHP fatal error in `wp-content/debug.log`.
4. Clear your caching plugin's cache — portal pages must not be cached per-user.
5. Confirm the `[wbam_advertiser_dashboard]` shortcode is in the page content.

### Tabs Missing from Dashboard

Some tabs are conditional:
- Classifieds tabs (`classifieds`, `inquiries`, `favorites`) only appear when the Classifieds module is enabled.
- The `links` tab only appears when the free WB Ad Manager plugin's `Partnership_Form` class is present.
- You can restrict tabs using the `show_tabs` attribute: `[wbam_advertiser_dashboard show_tabs="overview,ads,wallet,profile"]`.

---

## Analytics Issues

### No Data Showing

1. Confirm analytics is enabled at **Pro Settings → General → Enable analytics tracking**.
2. Allow up to 24 hours for data to accumulate on new installs.
3. Check date range filters — the default range may not include today if the filter was previously set to a past date.
4. Confirm the analytics JavaScript is loading (check browser console for errors).
5. If tracking logged-in users: enable **Track Logged-in Users** in **Pro Settings → Analytics & Privacy** — by default, only anonymous visitors are tracked.
6. If all traffic appears as bots, verify that **Bot Filtering** is not too aggressive for your test environment (curl, wget, and headless browsers are filtered).

### Impressions Not Recording

1. Confirm ads are actually being displayed (check the frontend with an ad blocker disabled).
2. Open browser DevTools → Network and look for AJAX requests to `wbam_track_event`.
3. Check for JavaScript console errors that may prevent the tracking script from firing.
4. Confirm GDPR consent is not blocking tracking — if **Require Cookie Consent** is enabled, analytics only fires after the visitor accepts the cookie notice.

---

## Common REST API Errors

| Error | Likely Cause | Fix |
|-------|-------------|-----|
| `rest_forbidden` | User lacks the required capability | Confirm the request includes a valid nonce or authentication credentials |
| `401 Unauthorized` | Missing or expired authentication | Include `X-WP-Nonce` header or use Application Passwords |
| `400 Bad Request` on campaign creation | Missing required fields or invalid `pricing_model` | Check that `pricing_model` is one of: `flat`, `cpm`, `cpc`, `cpm_cpc` |
| Stripe webhook returns `400` | Invalid or missing webhook secret | Re-copy the `whsec_...` value from Stripe Dashboard to **Pro Settings → Payments** |
| Stripe webhook returns `200` but wallet not credited | Idempotency key collision | Check for duplicate `payment_intent.succeeded` events in Stripe logs; each event should have a unique ID |

---

## Debug Mode

Enable WordPress debug logging before reporting an issue:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check the log at: `wp-content/debug.log`

---

## Getting Help

Pro customers receive priority support. When contacting support, include:

- Plugin version (visible in **Plugins → WB Ad Manager Pro**)
- WordPress version
- PHP version
- Error messages from `debug.log`
- Steps to reproduce the issue
- Your DB version (stored in the `wbam_pro_db_version` option; view with `wp option get wbam_pro_db_version` or look up the row in `wp_options`)
