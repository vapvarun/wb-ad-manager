---
title: Extend via Hooks
persona: Developer
tier: pro
one_job: Show a developer three real extension patterns using Pro hooks — each a self-contained drop-in for a site-specific plugin or mu-plugin.
outcome: Reader can add a custom submission field, extend the approval workflow, and hook classified upgrades using the real Pro action and filter API.
assumes: Pro activated with Ad Submissions, Classifieds, and Advertisers modules on; access to a custom plugin or mu-plugins directory; debug log access.
---

# Extend via Hooks

This flow walks a developer through three non-trivial extensions using real Pro hooks. Each example is a self-contained drop-in for a site-specific plugin or an `mu-plugins/` file. Budget about 45 minutes to work through all three.

## Before you start

- Pro is activated and at minimum the Ad Submissions, Classifieds, and Advertisers modules are on — see [Pro Installation & Requirements](../getting-started/pro-installation-requirements.md).
- You have access to either a custom plugin directory or `wp-content/mu-plugins/` where you can drop a PHP file.
- You have a way to read the PHP error log (either `wp-content/debug.log` or your hosting panel).

Full hook list: `docs/pro-developer/HOOKS.md` in the plugin. Every filter returns `WP_Error` to abort; every `_after_` action runs post-commit.

## Example 1 — Add a Promo Code field to the ad submission wizard

Scope: inject a custom field into Step 2 (Content) of the frontend ad submission wizard, persist it on submit, and read it back on the approved ad. Uses two hooks that work as a pair.

### Hooks used

| Hook | Type | What it does |
|------|------|--------------|
| `wbam_pro_ad_form_step_2_after_fields` | action | Render slot inside the Content step template |
| `wbam_pro_before_submit_ad` | filter | Runs before the submission record is saved; mutate `$ad_data` to persist extra fields |

### Code

```php
<?php
// wp-content/mu-plugins/site-ads-extend.php

/**
 * Render a Promo Code input inside Step 2 of the ad submission wizard.
 *
 * @param array $ad_data Current wizard state (pre-populated on edit).
 * @param bool  $is_edit Whether the user is editing an existing submission.
 */
add_action( 'wbam_pro_ad_form_step_2_after_fields', function ( $ad_data, $is_edit ) {
    $value = isset( $ad_data['promo_code'] ) ? $ad_data['promo_code'] : '';
    ?>
    <div class="wbam-form-row">
        <label for="promo_code"><?php esc_html_e( 'Promo Code (optional)', 'site-ads' ); ?></label>
        <input type="text" id="promo_code" name="promo_code"
               value="<?php echo esc_attr( $value ); ?>"
               maxlength="40"
               pattern="[A-Z0-9_-]+">
        <p class="description">
            <?php esc_html_e( 'Uppercase letters, digits, dashes, and underscores only.', 'site-ads' ); ?>
        </p>
    </div>
    <?php
}, 10, 2 );

/**
 * Persist the promo code when the submission is saved.
 *
 * Return a WP_Error to abort the submission and show the message to the advertiser.
 */
add_filter( 'wbam_pro_before_submit_ad', function ( $ad_data, $advertiser_id, $package_id ) {
    if ( empty( $_POST['promo_code'] ) ) {
        return $ad_data;
    }

    $code = sanitize_text_field( wp_unslash( $_POST['promo_code'] ) );
    $code = strtoupper( $code );

    if ( ! preg_match( '/^[A-Z0-9_-]{1,40}$/', $code ) ) {
        return new WP_Error(
            'invalid_promo_code',
            __( 'Promo code must be uppercase letters, digits, dashes, or underscores only.', 'site-ads' )
        );
    }

    $ad_data['promo_code'] = $code;
    return $ad_data;
}, 10, 3 );
```

### Verification

1. Log in as an advertiser and open the ad submission wizard from the Advertiser Dashboard.
2. Step to the **Content** tab. Confirm the Promo Code field renders under the existing fields.
3. Enter a lowercase value and submit. The submission aborts with the custom error message.
4. Enter a valid value and submit. The submission saves; look up the submission row in `wbam_ad_submissions` and confirm the `promo_code` column (or the serialized `extra_fields` blob, depending on your DB schema) contains the uppercased value.

## Example 2 — Expose a "sold" badge on the public classified REST response

Scope: add a boolean `is_sold` flag to the public REST payload that the classified single-page JS renders. Useful when you run a custom "Sold" post-meta flag and want the frontend to show a badge without a second AJAX call.

### Hook used

| Hook | Type | What it does |
|------|------|--------------|
| `wbam_pro_rest_prepare_classified_public` | filter | Fires on `Classified_API::prepare_public_response()`; shape the JSON sent to unauthenticated / non-owner viewers |

`wbam_pro_rest_prepare_classified_*` fires in `public → user → admin` order; hook the narrowest layer you need.

### Code

```php
<?php
// wp-content/mu-plugins/site-classifieds-rest.php

/**
 * Add an is_sold flag to the public REST shape of a classified.
 *
 * Scope: everyone who hits /wp-json/wbam-pro/v1/classifieds/{id} (public reads).
 *
 * @param array      $data       Prepared payload.
 * @param Classified $classified Source model.
 * @param bool       $full       Whether the "full" response variant was requested.
 */
add_filter( 'wbam_pro_rest_prepare_classified_public', function ( $data, $classified, $full ) {
    $post_id = (int) $classified->post_id;

    // _site_sold is a post-meta flag set by the seller via a custom "Mark as Sold"
    // button in the My Listings screen. Cast aggressively so the JSON is stable.
    $data['is_sold']   = (bool) get_post_meta( $post_id, '_site_sold', true );
    $data['sold_date'] = get_post_meta( $post_id, '_site_sold_date', true ) ?: null;

    return $data;
}, 10, 3 );
```

### Verification

1. Set `_site_sold = 1` and `_site_sold_date = 2026-01-15` on one classified post via `update_post_meta()` in WP-CLI.
2. In an incognito window, hit `/wp-json/wbam-pro/v1/classifieds/<post_id>`.
3. Confirm the JSON response contains `"is_sold": true` and `"sold_date": "2026-01-15"`.
4. Hit the same endpoint for a classified where the flag is unset. Confirm `is_sold` is `false` and `sold_date` is `null`.

## Example 3 — Grant onboarding credits when a new advertiser is created

Scope: every time a new advertiser account is created (self-registration or admin-created), grant a $5 welcome credit with an idempotency key so accidental re-runs never double-credit. This is a post-commit action with a wallet side effect — demonstrates integrating with the `Wallet_Manager` from outside Pro.

### Hook used

| Hook | Type | What it does |
|------|------|--------------|
| `wbam_pro_after_create_advertiser` | action | Fires post-commit after a new `Advertiser` row exists; safe for side effects |

### Code

```php
<?php
// wp-content/mu-plugins/site-advertiser-welcome.php

use WBAM_Pro\Modules\Wallet\Wallet_Manager;

/**
 * Grant $5.00 of welcome credit on advertiser creation.
 *
 * Uses an idempotency key derived from the advertiser ID so this action is
 * safe under retries; Wallet_Manager::credit() will reject a second grant with
 * the same key thanks to the UNIQUE constraint on the ledger.
 */
add_action( 'wbam_pro_after_create_advertiser', function ( $advertiser ) {
    // Defensive: Wallet_Manager only loads when the Wallet module is on.
    if ( ! class_exists( '\\WBAM_Pro\\Modules\\Wallet\\Wallet_Manager' ) ) {
        return;
    }

    $advertiser_id = (int) $advertiser->id;
    if ( $advertiser_id <= 0 ) {
        return;
    }

    Wallet_Manager::get_instance()->credit( array(
        'advertiser_id'   => $advertiser_id,
        'amount'          => 5.00,
        'type'            => 'credit',
        'reason'          => __( 'Welcome credit', 'site-ads' ),
        'idempotency_key' => 'site_welcome_' . $advertiser_id,
    ) );
}, 10, 1 );
```

### Verification

1. Register a new advertiser via the Advertiser Dashboard in an incognito window.
2. In the admin, open **WB Ads → Advertisers**, find the new account, and confirm the balance reads `$5.00`.
3. Open **WB Ads → Transactions** and confirm a ledger row with type `credit`, amount `$5.00`, reason "Welcome credit", and idempotency key `site_welcome_<id>`.
4. Call the same `wbam_pro_after_create_advertiser` action manually with the same advertiser (for example via WP-CLI `eval`). Confirm **no second credit is recorded** — the idempotency key blocks the double-credit.

## Verification — how to confirm the full flow worked

All three extensions must pass their own verification block above:

- Example 1: the Promo Code field renders, validates, and persists.
- Example 2: the `is_sold` flag appears in the public REST payload and reflects post-meta accurately.
- Example 3: new advertisers get exactly one $5 welcome credit, and the idempotency key prevents duplicates.

No PHP fatals in `wp-content/debug.log` after exercising all three flows.

## What to do next

- Browse the full hook surface in `docs/pro-developer/HOOKS.md` — write-path filters, REST shape filters, four wizard slot actions, and the `wbam_pro_free_setting_{key}` cross-plugin bridge.
- For campaign side effects (e.g., post to Slack when a campaign goes live), hook `wbam_pro_after_update_campaign` and inspect the `$old_data` status delta.
- For short-circuit patterns (e.g., archive instead of delete), filter `wbam_pro_before_delete_advertiser` and return non-null to skip the default delete.
- If you need a new hook that does not exist, open a PR against Pro — every filter / action follows the `wbam_pro_{verb}_{action}_{entity}` convention.
