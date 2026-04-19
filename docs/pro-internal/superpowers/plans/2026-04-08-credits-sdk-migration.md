# Credits SDK Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the entire Wallet_Manager + Payment system (~4000 lines) with Wbcom Credits SDK, making the plugin a pure credit consumer with zero payment code.

**Architecture:** Plugin registers 3 consumers (ad, classified, campaign) with the SDK. SDK handles all credit operations (balance, hold, deduct, refund), payment gateways (Stripe, PayPal, WooCommerce, subscriptions), and REST API. Plugin keeps only: billing cron, business logic for costs, and a settings page for credit mappings.

**Tech Stack:** Wbcom Credits SDK (PHP 8.1+, WP 6.5+), WordPress Settings API, existing plugin module system.

---

## File Structure

### New Files
- `vendor/wbcom-credits-sdk/` — SDK bundle (git submodule or copy)
- `includes/Core/class-credits-bridge.php` — Bridge class: registers consumers, maps advertiser_id ↔ user_id, provides helper methods
- `includes/Admin/class-credits-settings.php` — Admin settings tab: SDK enable/disable, WC product mappings, subscription mappings, purchase URL, low balance threshold

### Files to Modify
- `includes/Core/class-pro-plugin.php` — Remove wallet/payment module loading, add SDK initialization
- `includes/Core/class-pro-admin.php` — Remove payment settings tabs, add Credits settings tab
- `includes/Core/class-installer.php` — Remove `wbam_transactions` table creation (keep for migration check)
- `includes/Core/functions.php` — Update `wbam_is_wallet_enabled()` to check SDK
- `includes/Modules/Campaigns/class-campaign-manager.php` — Replace 4 Wallet_Manager calls with Credits::
- `includes/Modules/Memberships/class-membership-manager.php` — Replace debit/balance calls with Credits::
- `includes/Modules/Classifieds/class-classified.php` — Replace 2 debit calls
- `includes/Modules/Classifieds/class-classified-api.php` — Replace 6 balance/debit calls
- `includes/Modules/Classifieds/class-classified-shortcodes.php` — Replace 2 balance calls
- `includes/Modules/Classifieds/Shortcodes/class-ajax-handler.php` — Replace 9 balance/debit calls
- `includes/Modules/Advertisers/class-advertiser-shortcodes.php` — Replace 2 balance/debit calls
- `includes/Modules/Wallet/class-billing-manager.php` — Replace debit() with Credits::deduct()
- `includes/Modules/Notifications/class-email-notifications.php` — Update hook from wbam_wallet_credited to SDK hook
- `includes/Core/class-pro-abilities.php` — Replace 2 wallet calls
- `includes/Admin/class-transactions-list-table.php` — Query SDK ledger instead of wbam_transactions
- `templates/portal/tabs/wallet.php` — Rewrite: show SDK balance + ledger, link to purchase URL, remove add-funds modal
- `templates/portal/tabs/overview.php` — Replace Wallet_Manager balance call with Credits::
- `templates/portal/classified-form.php` — Replace balance display call
- `assets/js/portal.js` — Remove add-funds modal JS, update balance fetch to SDK REST

### Files to Delete (entire modules)
- `includes/Modules/Wallet/class-wallet-manager.php`
- `includes/Modules/Wallet/class-transaction.php`
- `includes/Modules/Wallet/class-wallet-api.php`
- `includes/Modules/Wallet/class-stripe-integration.php`
- `includes/Modules/Wallet/class-woocommerce-integration.php`
- `includes/Modules/Payments/class-stripe-handler.php`
- `includes/Modules/Payments/class-paypal-handler.php`
- `includes/Modules/Payments/class-razorpay-handler.php`
- `includes/Modules/Payments/class-woocommerce-handler.php`

---

## Task 1: Bundle SDK and Create Bridge Class

**Files:**
- Create: `vendor/wbcom-credits-sdk/` (git submodule)
- Create: `includes/Core/class-credits-bridge.php`
- Modify: `includes/Core/class-pro-plugin.php`

- [ ] **Step 1: Add SDK as git submodule**

```bash
cd /Users/varundubey/Local\ Sites/wp-ads/app/public/wp-content/plugins/wb-ad-manager-pro
git submodule add https://github.com/vapvarun/wbcom-credits-sdk.git vendor/wbcom-credits-sdk
```

- [ ] **Step 2: Create Credits Bridge class**

Create `includes/Core/class-credits-bridge.php`:

```php
<?php
namespace WBAM_Pro\Core;

use Wbcom\Credits\Credits;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Credits_Bridge {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Include SDK.
        if ( file_exists( WBAM_PRO_PATH . 'vendor/wbcom-credits-sdk/wbcom-credits-sdk.php' ) ) {
            require_once WBAM_PRO_PATH . 'vendor/wbcom-credits-sdk/wbcom-credits-sdk.php';
        }

        // Register with SDK.
        add_action( 'wbcom_credits_sdk_registry', array( $this, 'register_consumers' ) );

        // Bridge old hooks to SDK hooks for email notifications.
        add_action( 'wbcom_credits_topped_up', array( $this, 'bridge_credit_notification' ), 10, 4 );
        add_action( 'wbcom_credits_low', array( $this, 'bridge_low_balance_notification' ), 10, 3 );
    }

    public function register_consumers( $registry ) {
        $registry->register( array(
            'slug'      => 'wbam-pro',
            'prefix'    => 'wbam',
            'version'   => WBAM_PRO_VERSION,
            'file'      => WBAM_PRO_FILE,
            'user_type' => 'advertiser',
            'consumers' => array(
                array(
                    'id'        => 'ad_submission',
                    'label'     => __( 'Ad Submission', 'wb-ad-manager-pro' ),
                    'cost'      => array( $this, 'get_ad_cost' ),
                    'hold_on'   => 'wbam_ad_submitted',
                    'deduct_on' => 'wbam_ad_approved',
                    'refund_on' => 'wbam_ad_rejected',
                ),
                array(
                    'id'        => 'classified_listing',
                    'label'     => __( 'Classified Listing', 'wb-ad-manager-pro' ),
                    'cost'      => array( $this, 'get_classified_cost' ),
                    'hold_on'   => 'wbam_classified_submitted',
                    'deduct_on' => 'wbam_classified_approved',
                    'refund_on' => 'wbam_classified_rejected',
                ),
                array(
                    'id'        => 'campaign_budget',
                    'label'     => __( 'Campaign Budget', 'wb-ad-manager-pro' ),
                    'cost'      => array( $this, 'get_campaign_cost' ),
                    'hold_on'   => 'wbam_campaign_activated',
                    'deduct_on' => 'wbam_campaign_completed',
                    'refund_on' => 'wbam_campaign_cancelled',
                ),
            ),
            'settings'  => array(
                'low_threshold'       => absint( Settings_Helper::get( 'low_balance_threshold', 10 ) ),
                'purchase_url'        => Settings_Helper::get( 'credits_purchase_url', '' ),
                'admin_settings_hook' => 'wbam_credits_settings_tab',
            ),
        ) );
    }

    // --- Advertiser ↔ User ID helpers ---

    /**
     * Get user_id from advertiser_id.
     */
    public static function get_user_id( $advertiser_id ) {
        $advertiser = Advertiser_Manager::get_instance()->get( $advertiser_id );
        return $advertiser ? $advertiser->user_id : 0;
    }

    /**
     * Get advertiser_id from user_id.
     */
    public static function get_advertiser_id( $user_id ) {
        $advertiser = Advertiser_Manager::get_instance()->get_by_user( $user_id );
        return $advertiser ? $advertiser->id : 0;
    }

    // --- Balance helpers (thin wrappers for call-site convenience) ---

    /**
     * Get balance for an advertiser (by advertiser_id).
     */
    public static function get_balance( $advertiser_id ) {
        $user_id = self::get_user_id( $advertiser_id );
        if ( ! $user_id ) {
            return 0;
        }
        return Credits::get_balance( 'wbam-pro', $user_id );
    }

    /**
     * Hold credits (reserve on submission).
     */
    public static function hold( $advertiser_id, $amount, $item_id = 0, $note = '' ) {
        $user_id = self::get_user_id( $advertiser_id );
        if ( ! $user_id ) {
            return false;
        }
        return Credits::hold( 'wbam-pro', $user_id, $amount, $item_id, $note );
    }

    /**
     * Deduct credits (permanent charge).
     */
    public static function deduct( $advertiser_id, $amount, $item_id = 0, $note = '' ) {
        $user_id = self::get_user_id( $advertiser_id );
        if ( ! $user_id ) {
            return false;
        }
        return Credits::deduct( 'wbam-pro', $user_id, $amount, $item_id, $note );
    }

    /**
     * Refund credits (return held/charged amount).
     */
    public static function refund( $advertiser_id, $amount, $item_id = 0, $note = '' ) {
        $user_id = self::get_user_id( $advertiser_id );
        if ( ! $user_id ) {
            return false;
        }
        return Credits::refund( 'wbam-pro', $user_id, $amount, $item_id, $note );
    }

    /**
     * Get ledger/transaction history for an advertiser.
     */
    public static function get_ledger( $advertiser_id, $limit = 50, $offset = 0 ) {
        $user_id = self::get_user_id( $advertiser_id );
        if ( ! $user_id ) {
            return array();
        }
        return Credits::get_ledger( 'wbam-pro', $user_id, $limit, $offset );
    }

    /**
     * Check if credits system is enabled.
     */
    public static function is_enabled() {
        return Credits::is_enabled( 'wbam-pro' );
    }

    /**
     * Get purchase URL where users buy credits.
     */
    public static function get_purchase_url() {
        return Credits::get_purchase_url( 'wbam-pro' );
    }

    // --- Cost callbacks for consumers ---

    public function get_ad_cost( $item_id ) {
        $package_id = get_post_meta( $item_id, '_wbam_package_id', true );
        if ( ! $package_id ) {
            return 0;
        }
        $package = \WBAM_Pro\Modules\Packages\Package_Manager::get_instance()->get( absint( $package_id ) );
        return $package ? (int) $package->price : 0;
    }

    public function get_classified_cost( $item_id ) {
        // Cost determined at submission time, stored as meta.
        $cost = get_post_meta( $item_id, '_wbam_listing_cost', true );
        return $cost ? (int) $cost : 0;
    }

    public function get_campaign_cost( $item_id ) {
        $campaign = \WBAM_Pro\Modules\Campaigns\Campaign_Manager::get_instance()->get( $item_id );
        return $campaign ? (int) $campaign->budget : 0;
    }

    // --- Hook bridges for backward compat ---

    public function bridge_credit_notification( $slug, $user_id, $amount, $note ) {
        if ( 'wbam-pro' !== $slug ) {
            return;
        }
        // Fire old hook for email notifications that listen to it.
        do_action( 'wbam_credits_added', $user_id, $amount, $note );
    }

    public function bridge_low_balance_notification( $slug, $user_id, $balance ) {
        if ( 'wbam-pro' !== $slug ) {
            return;
        }
        $advertiser_id = self::get_advertiser_id( $user_id );
        if ( $advertiser_id ) {
            do_action( 'wbam_advertiser_low_balance', $advertiser_id, $balance );
        }
    }
}
```

- [ ] **Step 3: Wire SDK into Pro_Plugin**

In `includes/Core/class-pro-plugin.php`, replace wallet/payment module loading with SDK init:

```php
// REMOVE these lines (in register_modules or load_dependencies):
// require_once WBAM_PRO_PATH . 'includes/Modules/Wallet/class-wallet-manager.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Wallet/class-transaction.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Wallet/class-wallet-api.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Wallet/class-stripe-integration.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Wallet/class-woocommerce-integration.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Payments/class-stripe-handler.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Payments/class-paypal-handler.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Payments/class-razorpay-handler.php';
// require_once WBAM_PRO_PATH . 'includes/Modules/Payments/class-woocommerce-handler.php';

// ADD:
require_once WBAM_PRO_PATH . 'includes/Core/class-credits-bridge.php';
Credits_Bridge::get_instance()->init();
```

- [ ] **Step 4: Run `php -l` on new files**

```bash
php -l includes/Core/class-credits-bridge.php
```

Expected: No syntax errors

- [ ] **Step 5: Commit**

```bash
git add vendor/wbcom-credits-sdk includes/Core/class-credits-bridge.php includes/Core/class-pro-plugin.php
git commit -m "feat: Bundle Credits SDK and create bridge class with 3 consumers"
```

---

## Task 2: SDK Provides ALL Settings + Frontend UX (Plugin Just Hooks In)

**Key architecture decision:** The SDK itself should provide ALL admin settings UI and frontend purchase UX. Individual plugins should NOT duplicate this. The SDK already provides REST API — it should also provide:

### What SDK Should Own (build/update at SDK level — shared across ALL plugins)

**Admin Settings (SDK renders via `admin_settings_hook`):**
1. **Credit Packages CPT** — `wbcom_credit_package` (shared across all plugins)
   - Package name, credits amount, price, Stripe Price ID
   - Admin list table + add/edit form
2. **Stripe Settings** — test/live keys, webhook secret, mode toggle
3. **PayPal Settings** — client ID, secret (future SDK gateway)
4. **WooCommerce Mappings** — WC product → credits, subscription → recurring credits
5. **PMPro / MemberPress Mappings** — membership level → credits
6. **Balance Lookup** — admin tool (user ID → balance)
7. **Low Credit Threshold** — triggers email notification
8. **Purchase URL** — where users buy credits (WC shop page or custom)

**Frontend UX (SDK renders via shortcode/block):**
1. **Credit Balance Widget** — shows current balance (block: `credit-balance`)
2. **Buy Credits Page** — lists packages with Stripe/PayPal checkout buttons
3. **Credit History** — ledger view with entry types, amounts, dates
4. **Low Balance Prompt** — "You need more credits" with purchase link

**REST API (SDK already provides):**
- `GET /wbcom-credits/v1/{slug}/balance`
- `GET /wbcom-credits/v1/{slug}/history`
- `POST /wbcom-credits/v1/{slug}/topup` (admin)

### What This Plugin Does (Task 2 scope — minimal)

The plugin ONLY:
1. Tells SDK where to render settings: `'admin_settings_hook' => 'wbam_pro_settings_credits_tab'`
2. Adds a "Credits" tab in its own settings page that fires the hook
3. Removes ALL old payment settings tabs (Stripe, PayPal, Razorpay, WooCommerce, Manual)

**Files:**
- Modify: `includes/Core/class-pro-admin.php` — remove payment tabs, add Credits tab that fires SDK hook

- [ ] **Step 1: Update Pro_Admin settings tabs**

In `class-pro-admin.php`:
- Remove the Stripe settings section (lines ~4208-4400)
- Remove the PayPal settings section
- Remove the Razorpay settings section
- Remove the WooCommerce payment settings section
- Remove the Manual/Bank transfer settings section
- Add a new "Credits" tab that calls: `do_action('wbam_credits_settings_tab');`
- The SDK's registered `admin_settings_hook` renders the full settings UI

- [ ] **Step 2: Run `php -l` and commit**

```bash
php -l includes/Core/class-pro-admin.php
git add includes/Core/class-pro-admin.php
git commit -m "refactor: Replace payment settings with SDK Credits tab hook"
```

### SDK Enhancement Needed (separate task — update SDK repo)

The WP Career Board currently has credit packages + Stripe settings embedded in its own admin class. This should be extracted into the SDK so ALL plugins get the same UI automatically. Create a Basecamp card for SDK enhancement:

- [ ] **Step 3: Create Basecamp card for SDK admin UI extraction**

Card title: "[SDK] Extract admin settings UI into SDK — credit packages, Stripe, balance lookup"
Column: Ready for Development
Content: Move the AdminCredits pattern from WP Career Board into the SDK core. Every plugin that registers with the SDK gets the admin settings page automatically via the `admin_settings_hook`. This includes:
- Credit packages CPT + list table
- Stripe settings form
- Balance lookup tool
- WC/subscription/membership adapter mapping UI
- Low credit threshold
- Purchase URL configuration

---

## Task 3: Replace Wallet_Manager Calls in Campaign Module

**Files:**
- Modify: `includes/Modules/Campaigns/class-campaign-manager.php` (4 call sites)

- [ ] **Step 1: Read current campaign-manager.php**

Identify the 4 `Wallet_Manager` call sites at lines ~248, 269, 307, 376.

- [ ] **Step 2: Replace each call**

Pattern for each:
```php
// OLD:
$wallet = Wallet_Manager::get_instance();
$wallet->debit( $advertiser_id, $amount, 'campaign_reserve', $description );

// NEW:
use WBAM_Pro\Core\Credits_Bridge;
Credits_Bridge::hold( $advertiser_id, $amount, $campaign->id, $description );
```

Map wallet operations to SDK operations:
- Campaign activate (budget reservation) → `Credits_Bridge::hold()`
- Campaign complete (settle) → `Credits_Bridge::deduct()`
- Campaign cancel (refund unused) → `Credits_Bridge::refund()`
- Budget adjustment → `Credits_Bridge::deduct()` or `Credits_Bridge::refund()` based on direction

- [ ] **Step 3: Run `php -l` and commit**

```bash
php -l includes/Modules/Campaigns/class-campaign-manager.php
git add includes/Modules/Campaigns/class-campaign-manager.php
git commit -m "refactor: Replace Wallet_Manager with Credits_Bridge in campaign module"
```

---

## Task 4: Replace Wallet Calls in Classified Module

**Files:**
- Modify: `includes/Modules/Classifieds/class-classified.php` (2 calls)
- Modify: `includes/Modules/Classifieds/class-classified-api.php` (6 calls)
- Modify: `includes/Modules/Classifieds/class-classified-shortcodes.php` (2 calls)
- Modify: `includes/Modules/Classifieds/Shortcodes/class-ajax-handler.php` (9 calls)

- [ ] **Step 1: Replace in class-classified.php (lines ~1446, 1573)**

```php
// OLD:
$wallet = \WBAM_Pro\Modules\Wallet\Wallet_Manager::get_instance();
$wallet->debit( $advertiser_id, $cost, 'classified', $description );

// NEW:
Credits_Bridge::deduct( $advertiser_id, $cost, $classified_id, $description );
```

- [ ] **Step 2: Replace in class-classified-api.php (6 calls: lines ~807, 849, 992, 1013, 1053, 1074)**

Replace each `get_balance()` with `Credits_Bridge::get_balance()` and each `debit()` with `Credits_Bridge::deduct()`.

- [ ] **Step 3: Replace in class-classified-shortcodes.php (2 calls: lines ~826, 2245)**

Replace `get_balance()` calls with `Credits_Bridge::get_balance()`.

- [ ] **Step 4: Replace in class-ajax-handler.php (9 calls: lines ~264, 351, 698, 711, 761, 774, 946, 964, 1061)**

Replace all `get_balance()` with `Credits_Bridge::get_balance()` and all `debit()` with `Credits_Bridge::deduct()`.

- [ ] **Step 5: Run `php -l` on all 4 files and commit**

```bash
for f in class-classified.php class-classified-api.php class-classified-shortcodes.php Shortcodes/class-ajax-handler.php; do
  php -l "includes/Modules/Classifieds/$f"
done
git add includes/Modules/Classifieds/
git commit -m "refactor: Replace Wallet_Manager with Credits_Bridge in classified module"
```

---

## Task 5: Replace Wallet Calls in Remaining Modules

**Files:**
- Modify: `includes/Modules/Memberships/class-membership-manager.php` (3 calls)
- Modify: `includes/Modules/Advertisers/class-advertiser-shortcodes.php` (2 calls)
- Modify: `includes/Core/class-pro-admin.php` (5 calls)
- Modify: `includes/Core/class-pro-abilities.php` (2 calls)
- Modify: `includes/Modules/Wallet/class-billing-manager.php` (refactor internals)

- [ ] **Step 1: Membership manager — lines ~265, 287, 469**

Replace `$wallet->get_balance()` and `$wallet->debit()` with `Credits_Bridge::get_balance()` and `Credits_Bridge::deduct()`.

- [ ] **Step 2: Advertiser shortcodes — lines ~1912, 1943**

Replace wallet balance check and package purchase debit.

- [ ] **Step 3: Pro_Admin — lines ~660, 5256, 5830, 5889, 5921**

Replace admin approve/cancel payment, transaction management calls. For admin manual topup, use `Credits::topup('wbam-pro', $user_id, $amount, $note)` directly.

- [ ] **Step 4: Pro Abilities — lines ~1831, 1905**

Replace balance check and add-funds execution.

- [ ] **Step 5: Billing Manager — refactor to use Credits_Bridge**

Keep the cron and billing logic. Replace internal `$this->wallet->debit()` with `Credits_Bridge::deduct()`. Remove the `Wallet_Manager` dependency from constructor.

- [ ] **Step 6: Run `php -l` on all files and commit**

```bash
php -l includes/Modules/Memberships/class-membership-manager.php
php -l includes/Modules/Advertisers/class-advertiser-shortcodes.php
php -l includes/Core/class-pro-admin.php
php -l includes/Core/class-pro-abilities.php
php -l includes/Modules/Wallet/class-billing-manager.php
git add includes/Modules/ includes/Core/
git commit -m "refactor: Replace Wallet_Manager with Credits_Bridge in memberships, admin, abilities, billing"
```

---

## Task 6: Update Frontend Templates and JS

**Files:**
- Modify: `templates/portal/tabs/wallet.php` — major rewrite
- Modify: `templates/portal/tabs/overview.php` — balance display
- Modify: `templates/portal/classified-form.php` — balance display
- Modify: `assets/js/portal.js` — remove add-funds modal, update balance fetch
- Modify: `includes/Core/functions.php` — update `wbam_is_wallet_enabled()`

- [ ] **Step 1: Rewrite wallet.php template**

The wallet tab becomes a "Credits" tab showing:
- Current balance (from `Credits_Bridge::get_balance()`)
- "Buy Credits" button linking to `Credits_Bridge::get_purchase_url()`
- Transaction/ledger history (from `Credits_Bridge::get_ledger()`)
- NO add-funds modal, NO payment method selection, NO Stripe/PayPal UI

```php
<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

use WBAM_Pro\Core\Credits_Bridge;

if ( ! Credits_Bridge::is_enabled() ) { return; }

$balance     = Credits_Bridge::get_balance( $advertiser->id );
$purchase_url = Credits_Bridge::get_purchase_url();
$entries     = Credits_Bridge::get_ledger( $advertiser->id, 20, 0 );
?>
<div class="wbam-tab-content wbam-wallet">
    <!-- Balance Card -->
    <div class="wbam-wallet-header">
        <div class="wbam-wallet-balance">
            <span class="wbam-wallet-label"><?php esc_html_e( 'Available Credits', 'wb-ad-manager-pro' ); ?></span>
            <span class="wbam-wallet-amount"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
        </div>
        <?php if ( $purchase_url ) : ?>
        <a href="<?php echo esc_url( $purchase_url ); ?>" class="wbam-add-funds-btn">
            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            <?php esc_html_e( 'Buy Credits', 'wb-ad-manager-pro' ); ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- Transaction History -->
    <h3 class="wbam-section-title"><?php esc_html_e( 'Credit History', 'wb-ad-manager-pro' ); ?></h3>
    <?php if ( empty( $entries ) ) : ?>
        <div class="wbam-empty-state">
            <span class="dashicons dashicons-money-alt wbam-empty-state-icon" aria-hidden="true"></span>
            <h4><?php esc_html_e( 'No transactions yet', 'wb-ad-manager-pro' ); ?></h4>
            <p><?php esc_html_e( 'Your credit history will appear here.', 'wb-ad-manager-pro' ); ?></p>
        </div>
    <?php else : ?>
        <div class="wbam-transactions-list">
            <?php foreach ( $entries as $entry ) : ?>
            <div class="wbam-transaction-item">
                <div class="wbam-transaction-info">
                    <span class="wbam-transaction-type"><?php echo esc_html( ucfirst( $entry->entry_type ) ); ?></span>
                    <span class="wbam-transaction-note"><?php echo esc_html( $entry->note ); ?></span>
                </div>
                <div class="wbam-transaction-meta">
                    <span class="wbam-transaction-amount <?php echo $entry->amount > 0 ? 'wbam-amount-positive' : 'wbam-amount-negative'; ?>">
                        <?php echo $entry->amount > 0 ? '+' : ''; ?><?php echo esc_html( number_format_i18n( $entry->amount ) ); ?>
                    </span>
                    <span class="wbam-transaction-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->created_at ) ) ); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
```

- [ ] **Step 2: Update overview.php balance display (line ~32)**

```php
// OLD:
$wallet = \WBAM_Pro\Modules\Wallet\Wallet_Manager::get_instance();
$balance = $wallet->get_balance( $advertiser->id );

// NEW:
$balance = \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser->id );
```

- [ ] **Step 3: Update classified-form.php balance display (line ~576)**

Same pattern as overview.

- [ ] **Step 4: Update functions.php**

```php
// OLD:
function wbam_is_wallet_enabled() {
    $settings = get_option( 'wbam_pro_classifieds_settings', array() );
    return ! empty( $settings['enabled'] );
}

// NEW:
function wbam_is_wallet_enabled() {
    return \WBAM_Pro\Core\Credits_Bridge::is_enabled();
}
```

- [ ] **Step 5: Clean portal.js — remove add-funds modal code**

Remove the entire `WBAMAddFunds` handler (~lines 501-634 in portal.js). Remove modal open/close handlers. Keep balance display code but update it to fetch from SDK REST endpoint `/wp-json/wbcom-credits/v1/wbam-pro/balance`.

- [ ] **Step 6: Run `php -l` and commit**

```bash
php -l templates/portal/tabs/wallet.php
php -l templates/portal/tabs/overview.php
php -l templates/portal/classified-form.php
php -l includes/Core/functions.php
git add templates/ assets/js/portal.js includes/Core/functions.php
git commit -m "refactor: Rewrite wallet tab for Credits SDK — remove payment UI, show credit balance + ledger"
```

---

## Task 7: Update Admin Transactions List Table

**Files:**
- Modify: `includes/Admin/class-transactions-list-table.php`

- [ ] **Step 1: Replace transaction queries**

The list table currently queries `wbam_transactions` directly. Replace with SDK ledger queries via `Credits::get_ledger()`. Map SDK `entry_type` (topup/hold/deduction/refund) to display labels.

- [ ] **Step 2: Update column definitions**

SDK ledger has: id, user_id, item_id, entry_type, amount, note, created_at. Map these to the existing column structure (advertiser name via user_id lookup).

- [ ] **Step 3: Remove approve/cancel actions**

The old admin approve/cancel for pending manual payments is no longer needed — SDK handles payment completion through adapters.

- [ ] **Step 4: Run `php -l` and commit**

```bash
php -l includes/Admin/class-transactions-list-table.php
git add includes/Admin/class-transactions-list-table.php
git commit -m "refactor: Update transactions list table to query SDK ledger"
```

---

## Task 8: Update Email Notifications

**Files:**
- Modify: `includes/Modules/Notifications/class-email-notifications.php`
- Modify: `templates/emails/wallet-credited.php`

- [ ] **Step 1: Update notification hooks**

Replace `wbam_wallet_credited` listener with `wbam_credits_added` (bridged in Credits_Bridge). The bridge fires this when SDK `wbcom_credits_topped_up` triggers for our slug.

- [ ] **Step 2: Update email template**

Replace `$transaction` variable references with SDK ledger entry data. The email should show: amount added, new balance, date.

- [ ] **Step 3: Run `php -l` and commit**

```bash
php -l includes/Modules/Notifications/class-email-notifications.php
php -l templates/emails/wallet-credited.php
git add includes/Modules/Notifications/ templates/emails/
git commit -m "refactor: Update email notifications for Credits SDK hooks"
```

---

## Task 9: Delete Old Wallet + Payment Code

**Files:**
- Delete: `includes/Modules/Wallet/class-wallet-manager.php`
- Delete: `includes/Modules/Wallet/class-transaction.php`
- Delete: `includes/Modules/Wallet/class-wallet-api.php`
- Delete: `includes/Modules/Wallet/class-stripe-integration.php`
- Delete: `includes/Modules/Wallet/class-woocommerce-integration.php`
- Delete: `includes/Modules/Payments/class-stripe-handler.php`
- Delete: `includes/Modules/Payments/class-paypal-handler.php`
- Delete: `includes/Modules/Payments/class-razorpay-handler.php`
- Delete: `includes/Modules/Payments/class-woocommerce-handler.php`
- Modify: `includes/Core/class-pro-plugin.php` — remove require statements
- Modify: `includes/Core/class-installer.php` — remove `wbam_transactions` table creation

- [ ] **Step 1: Delete all wallet/payment files**

```bash
rm includes/Modules/Wallet/class-wallet-manager.php
rm includes/Modules/Wallet/class-transaction.php
rm includes/Modules/Wallet/class-wallet-api.php
rm includes/Modules/Wallet/class-stripe-integration.php
rm includes/Modules/Wallet/class-woocommerce-integration.php
rm includes/Modules/Payments/class-stripe-handler.php
rm includes/Modules/Payments/class-paypal-handler.php
rm includes/Modules/Payments/class-razorpay-handler.php
rm includes/Modules/Payments/class-woocommerce-handler.php
```

- [ ] **Step 2: Remove require statements from pro-plugin.php**

Remove all `require_once` lines for deleted files.

- [ ] **Step 3: Update installer — remove old table creation, keep billing-manager table**

In `class-installer.php`, remove the `CREATE TABLE wbam_transactions` statement. Keep `wbam_campaigns` and all other tables.

- [ ] **Step 4: Remove payment settings from pro-admin.php**

Remove Stripe, PayPal, Razorpay, WooCommerce, Manual payment settings sections.

- [ ] **Step 5: Verify no dangling references**

```bash
grep -r "Wallet_Manager" includes/ templates/ --include="*.php" | grep -v "class-billing-manager"
grep -r "Stripe_Handler\|PayPal_Handler\|Razorpay_Handler\|WooCommerce_Handler" includes/ templates/ --include="*.php"
grep -r "class-wallet-manager\|class-transaction\|class-wallet-api\|class-stripe-integration\|class-woocommerce-integration" includes/ --include="*.php"
```

Expected: zero matches for each.

- [ ] **Step 6: Run `php -l` on modified files and commit**

```bash
php -l includes/Core/class-pro-plugin.php
php -l includes/Core/class-installer.php
php -l includes/Core/class-pro-admin.php
git add -A
git commit -m "chore: Remove Wallet_Manager + Payment modules — replaced by Credits SDK"
```

---

## Task 10: Verify End-to-End Flows

- [ ] **Step 1: Verify all 14 portal tabs render (HTTP 200, 0 PHP errors)**

```bash
for tab in overview ads campaigns classifieds inquiries favorites following links messages wallet membership analytics share-of-voice profile; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -L "http://wp-ads.local/advertiser-dashboard/?tab=$tab" -b /tmp/wp-cookies.txt)
  errs=$(curl -s -L "http://wp-ads.local/advertiser-dashboard/?tab=$tab" -b /tmp/wp-cookies.txt | egrep -ci '(fatal error|warning:|notice:|parse error)')
  echo "$tab: HTTP $code | PHP errors: $errs"
done
```

Expected: all HTTP 200, 0 errors.

- [ ] **Step 2: Verify credits balance shows on wallet tab**

```bash
curl -s -L "http://wp-ads.local/advertiser-dashboard/?tab=wallet" -b /tmp/wp-cookies.txt | egrep -oi '(Available Credits|Buy Credits|Credit History)'
```

- [ ] **Step 3: Verify SDK REST endpoint works**

```bash
curl -s "http://wp-ads.local/wp-json/wbcom-credits/v1/wbam-pro/balance" -b /tmp/wp-cookies.txt
```

Expected: JSON with user_id, balance, enabled fields.

- [ ] **Step 4: Verify debug.log is clean**

```bash
cat wp-content/debug.log | wc -l
```

Expected: 0-1 lines.

- [ ] **Step 5: Verify no references to old wallet code remain**

```bash
grep -r "Wallet_Manager\|Stripe_Handler\|PayPal_Handler\|Razorpay_Handler\|WooCommerce_Handler\|wbam_transactions" includes/ templates/ assets/ --include="*.php" --include="*.js" | grep -v "billing-manager\|vendor/"
```

Expected: zero matches.

- [ ] **Step 6: Final commit and push**

```bash
git push origin 1.5.0
```

---

## Summary

| Task | Files | Effort |
|------|-------|--------|
| 1. Bundle SDK + Bridge | 3 new, 1 modify | Medium |
| 2. Credits Settings | 2 new, 1 modify | Medium |
| 3. Campaign module | 1 modify (4 calls) | Small |
| 4. Classified module | 4 modify (19 calls) | Medium |
| 5. Other modules | 5 modify (12 calls) | Medium |
| 6. Frontend templates + JS | 5 modify | Medium |
| 7. Admin transactions | 1 modify | Small |
| 8. Email notifications | 2 modify | Small |
| 9. Delete old code | 9 delete, 3 modify | Small |
| 10. Verify flows | 0 files (testing) | Small |

**Total: ~35 call sites to replace, 9 files to delete, ~4000 lines removed, ~300 lines added.**
