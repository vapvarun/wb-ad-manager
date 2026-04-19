# Unified Email Template System - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every email in the plugin go through the same HTML template system with child-theme override support — no inline string building, no plain-text fallbacks, no wpautop hacks.

**Architecture:** Keep two email classes (`Email_Notifications` and `Advertiser_Email_Notifications`) since they handle different hook domains with zero overlap. Unify them around a shared pattern: Template_Loader for template resolution (with child-theme override), `emails/header.php` + `emails/footer.php` wrapper, and a consistent `send()` method that always sends HTML.

**Tech Stack:** PHP, WordPress Settings API, Template_Loader class

---

## Current State

### Two email classes, two different patterns:

| | `Email_Notifications` | `Advertiser_Email_Notifications` |
|---|---|---|
| **Location** | `includes/Modules/Notifications/` | `includes/Modules/Advertisers/` |
| **Templates** | 8 HTML files exist, 9 missing (fall back to plain text) | 0 templates — all inline string concatenation |
| **Template loading** | `get_template()` → file or `get_default_template()` fallback | `get_email_header()` + string concat + `get_email_footer()` |
| **Send** | `send()` with wpautop hack for fallbacks | `send()` with wpautop for all messages |
| **Theme override** | Just added via Template_Loader (uncommitted) | None |
| **Emails** | 17 types | 12 types |
| **Hook domain** | Ad submissions, classifieds, wallet, campaigns, reviews, messaging, memberships | Ad post status transitions, fund requests, advertiser status, campaign budget/end, balance debits |

### Target state:

Both classes use the same pattern:
1. Every email has an HTML template file in `templates/emails/`
2. Templates use `Template_Loader::load_template('emails/header')` and `emails/footer`
3. Child themes can override any template at `wp-content/themes/{theme}/wb-ad-manager-pro/emails/{name}.php`
4. `send()` always sends `text/html` — no conditional, no wpautop
5. `get_default_template()` removed — templates are the source of truth
6. Developers get `wbam_email_before_send` filter on every email

---

## File Structure

### Files to modify:
- `includes/Modules/Notifications/class-email-notifications.php` — use Template_Loader, remove `get_default_template()`, simplify `send()`
- `includes/Modules/Advertisers/class-advertiser-email-notifications.php` — rewrite to use templates, remove inline string building
- `includes/Core/class-pro-plugin.php` — no changes (both classes already loaded)

### Template files to create (9 for Email_Notifications):
- `templates/emails/advertiser-approved.php`
- `templates/emails/advertiser-rejected.php`
- `templates/emails/admin-ad-submitted.php`
- `templates/emails/ad-changes-requested.php`
- `templates/emails/admin-classified-submitted.php`
- `templates/emails/classified-rejected.php`
- `templates/emails/follower-new-listing.php`
- `templates/emails/inquiry-received.php`
- `templates/emails/campaign-started.php`
- `templates/emails/campaign-completed.php`
- `templates/emails/campaign-budget-low.php`

### Template files to create (12 for Advertiser_Email_Notifications):
- `templates/emails/advertiser-ad-approved.php`
- `templates/emails/advertiser-ad-rejected.php`
- `templates/emails/advertiser-ad-paused.php`
- `templates/emails/advertiser-fund-approved.php`
- `templates/emails/advertiser-fund-rejected.php`
- `templates/emails/advertiser-account-approved.php`
- `templates/emails/advertiser-account-suspended.php`
- `templates/emails/advertiser-account-banned.php`
- `templates/emails/advertiser-account-reactivated.php`
- `templates/emails/advertiser-campaign-depleted.php`
- `templates/emails/advertiser-campaign-ended.php`
- `templates/emails/advertiser-low-balance.php`

### Existing templates (no changes needed, already correct pattern):
- `templates/emails/header.php`
- `templates/emails/footer.php`
- `templates/emails/ad-approved.php`
- `templates/emails/ad-rejected.php`
- `templates/emails/ad-submitted.php`
- `templates/emails/classified-approved.php`
- `templates/emails/classified-expiring.php`
- `templates/emails/wallet-credited.php`
- `templates/emails/wallet-low-balance.php`
- `templates/emails/receipt.php`

---

## Tasks

### Task 1: Fix Email_Notifications — Template_Loader + remove fallbacks

**Files:**
- Modify: `includes/Modules/Notifications/class-email-notifications.php`

This task converts `Email_Notifications` to use Template_Loader for theme overrides and simplifies `send()` to always use HTML.

- [ ] **Step 1: Update `get_template()` to use Template_Loader**

Replace the hardcoded path check with Template_Loader (supports child theme overrides):

```php
// At top of file, add:
use WBAM_Pro\Core\Template_Loader;

// Replace get_template() method:
private function get_template( $template, $vars = array() ) {
    // Use Template_Loader for theme override support.
    // Developers can override in: wp-content/themes/{theme}/wb-ad-manager-pro/emails/{template}.php
    $template_path = Template_Loader::get_template_path( 'emails/' . $template );

    if ( empty( $template_path ) ) {
        return $this->get_default_template( $template, $vars );
    }

    ob_start();
    extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
    include $template_path;
    return ob_get_clean();
}
```

- [ ] **Step 2: Simplify `send()` — always HTML, no wpautop hack**

Replace the send() method. Since all templates will produce HTML, always use `text/html`:

```php
private function send( $to, $subject, $message, $headers = array() ) {
    $default_headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
    );

    $headers = array_merge( $default_headers, $headers );

    /**
     * Filter: Email before sending.
     *
     * @param array $email {
     *     Email data.
     *     @type string $to      Recipient email.
     *     @type string $subject Email subject.
     *     @type string $message Email body (HTML).
     *     @type array  $headers Email headers.
     * }
     */
    $email = apply_filters(
        'wbam_email_before_send',
        array(
            'to'      => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
        )
    );

    return wp_mail( $email['to'], $email['subject'], $email['message'], $email['headers'] );
}
```

- [ ] **Step 3: Run php -l syntax check**

Run: `php -l includes/Modules/Notifications/class-email-notifications.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add includes/Modules/Notifications/class-email-notifications.php
git commit -m "Refactor Email_Notifications: Template_Loader for theme overrides, always HTML send"
```

---

### Task 2: Create 9 missing templates for Email_Notifications

**Files:**
- Create: 9 template files in `templates/emails/` (listed in File Structure above)

Each template follows the exact pattern of existing templates (e.g., `ad-approved.php`):
1. PHP docblock with `@var` annotations
2. ABSPATH guard
3. `use WBAM_Pro\Core\Template_Loader;`
4. Set `$email_heading`
5. `Template_Loader::load_template('emails/header', array('email_heading' => $email_heading));`
6. HTML content using CSS classes from header.php: `info-box`, `info-box-success`/`warning`/`danger`, `details-table`, `btn`
7. `Template_Loader::load_template('emails/footer');`

**Variable reference per template:**

| Template | Variables |
|---|---|
| `advertiser-approved` | `$user`, `$advertiser`, `$site_name`, `$login_url` |
| `advertiser-rejected` | `$user`, `$advertiser`, `$reason`, `$site_name` |
| `admin-ad-submitted` | `$submission`, `$ad`, `$advertiser`, `$review_url`, `$site_name` |
| `ad-changes-requested` | `$user`, `$ad`, `$submission`, `$notes`, `$edit_url`, `$site_name` |
| `admin-classified-submitted` | `$classified`, `$advertiser`, `$review_url`, `$site_name` |
| `classified-rejected` | `$user`, `$classified`, `$reason`, `$site_name` |
| `follower-new-listing` | `$user`, `$advertiser`, `$classified`, `$seller_name`, `$view_url`, `$profile_url`, `$site_name` |
| `inquiry-received` | `$inquiry` (->sender_name, ->sender_email, ->message), `$classified` (->get_title()), `$site_name` |
| `campaign-started` | `$user`, `$campaign` (->get_name()), `$site_name` |
| `campaign-completed` | `$user`, `$campaign`, `$stats_url`, `$site_name` |
| `campaign-budget-low` | `$user`, `$campaign`, `$add_funds`, `$site_name` |

- [ ] **Step 1: Review the 9 template files already created by agent**

The agent already created these files. Review each for:
- Correct variable usage (matches the table above)
- All output escaped (esc_html, esc_attr, esc_url)
- All strings translated with `__()` / `esc_html_e()` + `'wb-ad-manager-pro'` text domain
- Template_Loader::load_template calls for header/footer
- Proper CSS class usage from header.php

- [ ] **Step 2: Run php -l on all 9 files**

Run: `for f in templates/emails/advertiser-approved.php templates/emails/advertiser-rejected.php templates/emails/admin-ad-submitted.php templates/emails/ad-changes-requested.php templates/emails/admin-classified-submitted.php templates/emails/classified-rejected.php templates/emails/follower-new-listing.php templates/emails/inquiry-received.php templates/emails/campaign-started.php templates/emails/campaign-completed.php templates/emails/campaign-budget-low.php; do php -l "$f"; done`

Expected: All pass with no syntax errors

- [ ] **Step 3: Commit**

```bash
git add templates/emails/advertiser-approved.php templates/emails/advertiser-rejected.php templates/emails/admin-ad-submitted.php templates/emails/ad-changes-requested.php templates/emails/admin-classified-submitted.php templates/emails/classified-rejected.php templates/emails/follower-new-listing.php templates/emails/inquiry-received.php templates/emails/campaign-started.php templates/emails/campaign-completed.php templates/emails/campaign-budget-low.php
git commit -m "Add 9 missing HTML email templates for Email_Notifications"
```

---

### Task 3: Remove `get_default_template()` from Email_Notifications

**Files:**
- Modify: `includes/Modules/Notifications/class-email-notifications.php`

Now that all 17 templates have files, the plain-text fallback method is dead code.

- [ ] **Step 1: Update `get_template()` to remove fallback call**

Since all templates now have files, change the fallback to return empty string (Template_Loader handles missing files with WP_DEBUG comment):

```php
private function get_template( $template, $vars = array() ) {
    $template_path = Template_Loader::get_template_path( 'emails/' . $template );

    if ( empty( $template_path ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WBAM: Email template not found: ' . $template );
        }
        return '';
    }

    ob_start();
    extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
    include $template_path;
    return ob_get_clean();
}
```

- [ ] **Step 2: Remove `get_default_template()` method entirely**

Delete the entire `get_default_template()` method (~200 lines of plain-text fallback strings).

- [ ] **Step 3: Run php -l**

Run: `php -l includes/Modules/Notifications/class-email-notifications.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add includes/Modules/Notifications/class-email-notifications.php
git commit -m "Remove get_default_template() — all emails now use HTML template files"
```

---

### Task 4: Refactor Advertiser_Email_Notifications to use templates

**Files:**
- Modify: `includes/Modules/Advertisers/class-advertiser-email-notifications.php`

Rewrite the class to use the same template system. Replace inline string building with template loading. Replace the custom `get_email_header()`/`get_email_footer()` with Template_Loader. Unify `send()` to always use `text/html`.

- [ ] **Step 1: Add Template_Loader import and `get_template()` method**

Add at top:
```php
use WBAM_Pro\Core\Template_Loader;
```

Add private method (same pattern as Email_Notifications):
```php
private function get_template( $template, $vars = array() ) {
    $template_path = Template_Loader::get_template_path( 'emails/' . $template );

    if ( empty( $template_path ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WBAM: Email template not found: ' . $template );
        }
        return '';
    }

    ob_start();
    extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
    include $template_path;
    return ob_get_clean();
}
```

- [ ] **Step 2: Simplify `send()` — always HTML, use same filter hook**

Replace send() with:
```php
private function send( $to, $subject, $message ) {
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
    );

    /**
     * Filter: Email before sending.
     *
     * @param array $email Email data.
     */
    $email = apply_filters(
        'wbam_email_before_send',
        array(
            'to'      => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
        )
    );

    return wp_mail( $email['to'], $email['subject'], $email['message'], $email['headers'] );
}
```

Note: uses the SAME `wbam_email_before_send` filter as `Email_Notifications` — developers get one filter for all emails.

- [ ] **Step 3: Rewrite each email method to use templates**

For each of the 12 email methods, replace the inline string building with `$this->get_template()`. Example for `send_ad_approved()`:

**Before:**
```php
private function send_ad_approved( $advertiser, $post ) {
    $user = $advertiser->get_user();
    if ( ! $user ) { return; }
    $site_name = get_bloginfo( 'name' );
    $subject = sprintf(...);
    $message = $this->get_email_header( $advertiser );
    $message .= sprintf(...);
    // ... 15 lines of string concatenation
    $message .= $this->get_email_footer();
    $this->send( $user->user_email, $subject, $message );
}
```

**After:**
```php
private function send_ad_approved( $advertiser, $post ) {
    $user = $advertiser->get_user();
    if ( ! $user ) { return; }

    $subject = sprintf(
        /* translators: %1$s: ad title, %2$s: site name */
        __( 'Your ad "%1$s" has been approved - %2$s', 'wb-ad-manager-pro' ),
        $post->post_title,
        get_bloginfo( 'name' )
    );

    $message = $this->get_template(
        'advertiser-ad-approved',
        array(
            'user'       => $user,
            'advertiser' => $advertiser,
            'ad'         => $post,
            'site_name'  => get_bloginfo( 'name' ),
        )
    );

    $this->send( $user->user_email, $subject, $message );
}
```

Apply this pattern to all 12 methods:

| Method | Template name | Variables |
|---|---|---|
| `send_ad_approved()` | `advertiser-ad-approved` | `$user`, `$advertiser`, `$ad`, `$site_name` |
| `send_ad_rejected()` | `advertiser-ad-rejected` | `$user`, `$advertiser`, `$ad`, `$reason`, `$site_name` |
| `send_ad_paused()` | `advertiser-ad-paused` | `$user`, `$advertiser`, `$ad`, `$site_name` |
| `fund_request_approved()` | `advertiser-fund-approved` | `$user`, `$advertiser`, `$amount`, `$site_name` |
| `fund_request_rejected()` | `advertiser-fund-rejected` | `$user`, `$advertiser`, `$reason`, `$site_name` |
| `send_account_approved()` | `advertiser-account-approved` | `$user`, `$advertiser`, `$site_name` |
| `send_account_suspended()` | `advertiser-account-suspended` | `$user`, `$advertiser`, `$site_name` |
| `send_account_banned()` | `advertiser-account-banned` | `$user`, `$advertiser`, `$site_name` |
| `send_account_reactivated()` | `advertiser-account-reactivated` | `$user`, `$advertiser`, `$site_name` |
| `campaign_budget_depleted()` | `advertiser-campaign-depleted` | `$user`, `$advertiser`, `$campaign` (raw DB row), `$site_name` |
| `campaign_ended()` | `advertiser-campaign-ended` | `$user`, `$advertiser`, `$campaign` (raw DB row), `$site_name` |
| `check_low_balance()` | `advertiser-low-balance` | `$user`, `$advertiser`, `$site_name` |

- [ ] **Step 4: Remove `get_email_header()`, `get_email_footer()`, `get_dashboard_url()` methods**

These are no longer needed — header/footer come from templates, dashboard URLs are built in templates using `wbam_get_portal_url()`.

Keep `should_notify()` — it handles advertiser notification preferences (useful business logic).

- [ ] **Step 5: Run php -l**

Run: `php -l includes/Modules/Advertisers/class-advertiser-email-notifications.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add includes/Modules/Advertisers/class-advertiser-email-notifications.php
git commit -m "Refactor Advertiser_Email_Notifications to use HTML template system"
```

---

### Task 5: Create 12 templates for Advertiser_Email_Notifications

**Files:**
- Create: 12 template files in `templates/emails/` (listed in File Structure above)

Same pattern as Task 2. Each template uses header/footer wrapper, CSS classes, proper escaping.

**Variable reference per template:**

| Template | Key content |
|---|---|
| `advertiser-ad-approved` | Ad approved & live, ad title in details-table, "View Dashboard" btn |
| `advertiser-ad-rejected` | Ad needs changes, reason in info-box-danger, "Edit Ad" btn |
| `advertiser-ad-paused` | Ad paused, could be budget/schedule, "Manage Ads" btn |
| `advertiser-fund-approved` | Funds credited, amount + new balance in details-table, "View Wallet" btn |
| `advertiser-fund-rejected` | Fund request failed, reason if provided, "Try Again" btn |
| `advertiser-account-approved` | Welcome, feature list, "Get Started" btn |
| `advertiser-account-suspended` | Account suspended, contact support |
| `advertiser-account-banned` | Account terminated, remaining balance info |
| `advertiser-account-reactivated` | Account restored, "Access Dashboard" btn |
| `advertiser-campaign-depleted` | Budget used up, campaign name + spent in details-table, "Manage Campaigns" btn |
| `advertiser-campaign-ended` | Campaign completed, summary stats, "View Analytics" btn |
| `advertiser-low-balance` | Balance warning with amount, "Add Funds" btn in info-box-warning |

Note: `campaign_budget_depleted` and `campaign_ended` receive `$campaign` as a raw DB row (not a Campaign object), so templates should access `$campaign->name`, `$campaign->spent`, `$campaign->end_date` directly.

- [ ] **Step 1: Create all 12 template files**

- [ ] **Step 2: Run php -l on all 12 files**

- [ ] **Step 3: Commit**

```bash
git add templates/emails/advertiser-ad-approved.php templates/emails/advertiser-ad-rejected.php templates/emails/advertiser-ad-paused.php templates/emails/advertiser-fund-approved.php templates/emails/advertiser-fund-rejected.php templates/emails/advertiser-account-approved.php templates/emails/advertiser-account-suspended.php templates/emails/advertiser-account-banned.php templates/emails/advertiser-account-reactivated.php templates/emails/advertiser-campaign-depleted.php templates/emails/advertiser-campaign-ended.php templates/emails/advertiser-low-balance.php
git commit -m "Add 12 HTML email templates for Advertiser_Email_Notifications"
```

---

### Task 6: Final cleanup and verify

**Files:**
- Modify: `includes/Modules/Notifications/class-email-notifications.php` (if any cleanup remains)

- [ ] **Step 1: Verify all 29 emails have template files**

Run: `ls -1 templates/emails/*.php | wc -l`
Expected: 31 (29 email templates + header.php + footer.php)

- [ ] **Step 2: Verify theme override works**

Create a test override: `mkdir -p /tmp/test-theme/wb-ad-manager-pro/emails && cp templates/emails/ad-approved.php /tmp/test-theme/wb-ad-manager-pro/emails/ad-approved.php`

The Template_Loader should find the theme copy first when that theme is active.

- [ ] **Step 3: Run php -l on all modified PHP files**

```bash
php -l includes/Modules/Notifications/class-email-notifications.php
php -l includes/Modules/Advertisers/class-advertiser-email-notifications.php
```

- [ ] **Step 4: Final commit**

```bash
git commit -m "Complete unified email template system — all 29 emails use HTML templates with theme override support"
```

---

## Summary

| What | Count |
|---|---|
| Template files to create | 21 (9 + 12) |
| Template files already exist | 10 (8 content + header + footer) |
| Total template files | 31 |
| PHP classes modified | 2 |
| Code removed | ~200 lines (`get_default_template()`) + ~150 lines (inline string building) |
| Commits | 6 |

## Developer Experience After This

```
# Override any email template in child theme:
wp-content/themes/my-theme/wb-ad-manager-pro/emails/ad-approved.php

# Filter any email before send:
add_filter('wbam_email_before_send', function($email) {
    // Modify $email['to'], $email['subject'], $email['message'], $email['headers']
    return $email;
});

# All templates use same header/footer and CSS classes
# Logo and primary color configurable via settings
```
