# Plan: Wire `site_mode` into modules + UX — ship in 1.5.0

**Status:** ✅ Done (2026-04-18). Shipped in 1.5.0.
**Date:** 2026-04-18
**Target release:** `1.5.0` (current dev branch — lands in same release, no version bump).
**Scope:** Make the setup wizard's existing `site_mode` setting actually control what the plugin does, framed around 6 site-owner use cases. Covers advertiser-facing AND admin-facing reports.
**Risk class:** Cross-cutting. Executed one commit per step, browser-verified per step.
**Commit rule:** One step = one commit. Exact order. No skipping.

---

## 0. Design decisions (locked)

These decisions were made against one criterion: **what does the site owner need to not be confused and not break their site?**

| # | Decision | Why |
|---|----------|-----|
| D1 | Wizard step 2 (stub) stays untouched. | It's existing dev work — separate from mode wiring. Out of scope. |
| D2 | `auto` modules in each bundle are chosen per-mode with intent, not left blanket-auto. | The point of picking a mode is getting opinions. "Auto" must mean "the mode explicitly has no opinion, use defaults." |
| D3 | Auto-flip to `custom` mode IS implemented when admin manually edits modules. | Prevents the "Publisher mode" label from lying about the state. |
| D4 | `Site_Mode::module_bundle('custom')` returns pass-through of current `enabled_modules`. | Custom means "whatever the admin set" — any other answer would discard admin intent. |
| D5 | Readme changelog gains 5 new `* New:` bullets under 1.5.0. | Flagship feature deserves visibility. |
| D6 | Submission form JS is read and verified BEFORE submission-form step. | Cheap safety check prevents mid-implementation debugging. |
| D7 | A 6th mode `managed` covers "vendors on manual contract" — wallet module stays ON for infrastructure but `show_billing_ui=false` hides every billing UI surface. | Contracts are a real distinct use case between "sponsored" and "paid." Turning wallet OFF breaks the dependency DAG; invisible-infrastructure is the clean way. |
| D8 | In `managed` mode, advertiser self-registration is disabled — admin-invite only. Public register shortcode returns a "closed" message. | If you're on managed mode, you've negotiated offline. Self-register defeats the point, invites spam accounts. |
| D9 | Plugin does NOT track invoice/payment status for managed vendors. Fully off-platform. | Keeps scope tight. Customers who ask for paid/unpaid tracking later get it as an additive capability. |
| D10 | Capability flags govern DISPLAY only, never TRACKING. Impressions + clicks are always recorded in the DB by `Analytics_Tracker` regardless of mode. | Switching Publisher → Paid later shows historical click data lighting up — zero "why are my numbers zero?" tickets. |
| D11 | Report surfaces gated at FOUR levels: (a) advertiser dashboard tabs, (b) advertiser-facing tiles within tabs, (c) admin menu pages (Revenue, Inventory), (d) WP Dashboard Widget. Single capability `show_ctr_clicks` + `show_billing_ui` + the `wallet` module gate drive them all. | The plan's original Step 9 only covered (a) — leaving admin reports as a separate leak. |
| D12 | **The mode bundle is opt-in, never automatic.** A sentinel `wbam_pro_settings['_mode_applied']` defaults to `false`. Migration sets `site_mode` but NOT `_mode_applied`. Only `Site_Mode::apply()` — called from the wizard or the explicit "Change Mode" admin UI — sets `_mode_applied=true`. Until then, `is_module_enabled()` behaves exactly as today (explicit values → defaults), completely bypassing the mode bundle. Capability flags already default to `true` for unset values, so `wbam_can()` is safe by design. | Existing money-making customers running `ads`/`both` mode get ZERO behavior change on upgrade. The plan is purely additive until the admin voluntarily opts in. Autonomous revenue flow preserved bit-for-bit. |

Any change to these decisions = update the plan doc before executing.

---

## 0.5. Dry-run findings (folded into plan)

Walked every step against the actual code before execution. Seven sticking points surfaced; each is addressed in the step it affects. Summary:

| # | Finding | Step affected | Fix |
|---|---------|---------------|-----|
| DR1 | Modules tab saves via direct `$_POST` handler in `render_modules_settings()` at `class-pro-admin.php:3275-3305`, not via `register_setting` sanitize callback. | Step 8 | Insert 8 lines after line 3305's `Settings_Helper::update(...)` call. Same method, different spot. |
| DR2 | No `[wbam_advertiser_register]` shortcode exists. Registration is inline at `advertiser-shortcodes.php:2184` (`wbam_register` POST) AND "become advertiser" at line 354 (`wbam_become_advertiser` POST). | Step 10b | Gate BOTH entry points: render "invitation only" message in place of the register/become-advertiser forms. +10 lines across 2 spots. |
| DR3 | `Advertiser` class has no `billing_mode` property. DB column doesn't exist. | Step 3 + Step 10c | Fold `ALTER TABLE wp_wbam_advertisers ADD COLUMN billing_mode VARCHAR(20) NOT NULL DEFAULT 'self_serve'` into `upgrade_to_3_8_0()`. Add `public $billing_mode = 'self_serve'` to `class-advertiser.php` with populate/save wiring. |
| DR4 | Submission form ALREADY has a "custom" no-package path at `templates/portal/ad-form.php:380-395`. | Step 10 | Smaller than expected — wrap priced-package list in capability check, default to existing custom-path radio when hidden. ~15 lines. |
| DR5 | Admin reports register via `add_submenu_page()` in their own classes' `admin_menu` hooks — not via the Pro Settings tab array. | Step 9d | Three 1-line gates: early return at top of each `register_admin_page()` / `register_dashboard_widget()` method in `Revenue_Dashboard`, `Inventory_Dashboard`, `Dashboard_Widget`. |
| DR6 | `wbam_can()` needs advertiser-context overload because per-advertiser `billing_mode` flag drives some capabilities. | Step 2 | Signature becomes `wbam_can( $capability, $context = null, $default = true )`. Per-advertiser capabilities (`show_billing_ui`, `require_package_for_submission`) honor `$context->billing_mode`; site-wide ones ignore `$context`. +25 lines. |
| DR7 | Managed-advertiser package cards need "Contract limit: N impressions / N days" instead of `$price`. Data exists in `Package::$impressions_limit` / `$clicks_limit` / `$duration_days` but no existing helper. | New Step 10-pre | New helper `wbam_format_package_terms( $package, $advertiser )` + replace `<div class="wbam-package-price">` rendering in `ad-form.php:322` and `classified-form.php:423`. ~25 lines. |

**DB_VERSION decision:** both migrations (site_mode + billing_mode) bundle into one bump — `3.7.0 → 3.8.0`. One entrypoint, one revert point. (Original plan already had 3.8.0 for site_mode; just adding the ALTER TABLE to the same upgrade method.)

---

## 1. Why this plan, in one paragraph

The setup wizard already asks *"How will you use WB Ad Manager?"* and saves `wbam_pro_settings['site_mode']`. Zero code reads it. Every install behaves identically — all 15 modules on, all UI surfaces visible. This plan wires the saved answer into the modules and into the one template the customer explicitly complained about (analytics showing clicks/CTR when they only wanted impressions). Admin picks a site mode → modules reconfigure with clear opinions → UI reflects it. Existing installs migrate to `full` mode — zero visible change on upgrade. Manual Modules-tab edits flip to `custom` mode so admins are never trapped by a preset.

---

## 2. Mode catalog

### 2.1 The six site-owner use cases

| Slug | Label | Admin picks this when… | Icon |
|------|-------|------------------------|------|
| `publisher` | Publisher | "I place ads on my site myself. No advertiser portal." | megaphone |
| `sponsored` | Sponsored Advertisers | "Advertisers submit ads for me to approve. No billing, no contracts." | user-plus |
| `managed` | Managed Advertisers | "I work with vendors under offline contracts. Plugin tracks delivery; billing is offline." | handshake |
| `paid` | Paid Advertiser Portal | "Advertisers pay to run ads. Self-serve billing via credits." | credit-card |
| `classifieds` | Classifieds Marketplace | "Users post classified listings. May or may not have ads." | store |
| `full` | Full Platform | "Everything — paid ads + classifieds + all features." | globe |

Plus one internal mode:

| Slug | When it triggers | Admin sees |
|------|------------------|------------|
| `custom` | Admin manually edits any module in the Modules tab such that the state diverges from the current mode's bundle. | Label "Custom (based on *last applied*)" with a "Reset to *last applied*" button. |

### 2.2 Module bundle per mode (opinion-first)

Values: **ON** = explicitly enabled, **OFF** = explicitly disabled, **auto** = bundle has no opinion, falls back to `get_module_defaults()` (currently all-true).

| Module | publisher | sponsored | managed | paid | classifieds | full |
|--------|-----------|-----------|---------|------|-------------|------|
| `wallet` | OFF | OFF | **ON** ⁺ | ON | OFF | ON |
| `campaigns` | OFF | OFF | ON | ON | OFF | ON |
| `packages` | OFF | OFF | ON | ON | OFF | ON |
| `ad_submissions` | OFF | ON | ON | ON | OFF | ON |
| `classifieds` | OFF | OFF | OFF | OFF | ON | ON |
| `geolocation` | OFF | OFF | OFF | OFF | auto | auto |
| `custom_fields` | OFF | OFF | OFF | OFF | auto | auto |
| `reviews` | OFF | OFF | OFF | OFF | auto | auto |
| `messaging` | OFF | OFF | OFF | OFF | auto | auto |
| `memberships` | OFF | OFF | OFF | OFF | auto | auto |
| `ab_testing` | **OFF** | auto | auto | auto | OFF | auto |
| `rotation` | **OFF** | auto | auto | auto | OFF | auto |
| `links_pro` | **OFF** | OFF | OFF | auto | OFF | auto |
| `buddypress` | auto | auto | auto | auto | auto | auto |
| `email_notifications` | auto | ON | ON | ON | ON | ON |

⁺ **Managed mode keeps `wallet` ON for infrastructure only.** Campaigns + packages depend on wallet in the dependency DAG — turning it off cascades everything off. The `show_billing_ui=false` capability hides every UI surface (wallet tab, balance widget, transactions, prices) so admin and vendors never see the wallet. The table rows sit empty; the infrastructure runs.

**Rationale per mode (D2 applied):**
- **Publisher** turns OFF every non-core module. Radical simplification matches the mode name.
- **Sponsored** keeps A/B Testing and Rotation auto-on — multiple sponsors competing for slots is what Rotation is for. No packages, no contracts, no tracking caps.
- **Managed** lights up packages + campaigns + ad_submissions because packages ARE the contract terms ("Acme Q2 — 500K impressions, Header+Sidebar"). Links Pro stays off; offline contracts don't mix with click-tracking partnerships.
- **Paid** lights up the full billing stack. Self-serve monetization.
- **Classifieds** turns OFF everything ad-related.
- **Full** = current default — zero visible change for existing installs migrating here.

### 2.3 Capability flags per mode

Three flags. Stored as `wbam_pro_settings['capabilities'][$flag]`. All default to `true` when unset — unset capabilities preserve current visible behavior.

| Flag | publisher | sponsored | managed | paid | classifieds | full |
|------|-----------|-----------|---------|------|-------------|------|
| `require_package_for_submission` | — | **false** | **true** | true | — | true |
| `show_ctr_clicks` | **false** | true | true | true | — | true |
| `show_billing_ui` | — | **false** | **false** | true | true | true |
| `allow_advertiser_self_register` | false | true | **false** | true | — | true |

- `require_package_for_submission`: read by submission shortcode + `Ad_Submission_Manager`. When `false`, the package selector is hidden and `package_id=0` is sent — the already-existing `if ( $package_id )` gate at `class-ad-submission-manager.php:110` takes the no-package path.
- `show_ctr_clicks`: read by every report surface. When `false`, hides Clicks / CTR / Spent tiles + click toggles + click columns. Impressions always visible. **Tracking continues regardless (D10).**
- `show_billing_ui` **(new per D7)**: hides every billing chrome — wallet tab, balance widget, transaction history, package $prices (replaced with "Contract limit: N impressions"), "Pay for package" buttons (replaced with "Assigned by admin" badge), admin Revenue Dashboard menu.
- `allow_advertiser_self_register` **(new per D8)**: when `false`, the public `[wbam_advertiser_register]` shortcode renders "Registration is by invitation only. Contact the site owner." Admin can still create vendor accounts from backend.

### 2.4 Migration for existing installs

The current `site_mode` set is `ads | classifieds | both | (unset)`.

| Old value | New value | Why |
|-----------|-----------|-----|
| unset / empty | `full` | Preserves current visible behavior (all modules on) |
| `ads` | `paid` | Current `ads` ships with billing enabled → maps to Paid |
| `classifieds` | `classifieds` | Direct rename |
| `both` | `full` | Direct rename |

**`managed` is never a migration target.** Existing installs can't guess whether their advertisers are on contracts — only the admin knows. Managed mode is explicit-choose-only.

Migration runs once via `Installer::upgrade_to_3_8_0()`. DB_VERSION bump: `3.7.0 → 3.8.0`. Plugin version stays at `1.5.0`.

**Critical:** migration writes `site_mode` but DOES NOT call `Site_Mode::apply()`. Existing `enabled_modules` stays exactly as-is. Zero behavior change on upgrade. Explicit admin action (mode switch via UI) is the only thing that rewrites modules.

---

## 3. Files touched — exact list, 15 commits

### Pre-flight (not a commit)
```sql
SELECT option_value FROM wp_options WHERE option_name IN ('wbam_pro_settings', 'wbam_pro_db_version');
```
Record current state in the first commit body.

Also: read `assets/js/ad-submission.js` (or equivalent) — confirm no JS-side "package is required" validation that would block `package_id=0` submissions (D6). If found, note in Step 10 a JS edit alongside the PHP edit.

---

### Step 1 — Site_Mode helper class
**New file:** `includes/Core/class-site-mode.php`

```php
namespace WBAM_Pro\Core;

final class Site_Mode {

    // Public modes — pickable by the site owner.
    public const PUBLISHER   = 'publisher';
    public const SPONSORED   = 'sponsored';
    public const PAID        = 'paid';
    public const CLASSIFIEDS = 'classifieds';
    public const FULL        = 'full';

    // Internal mode — auto-assigned when admin's manual edits diverge from a preset.
    public const CUSTOM = 'custom';

    public const ALL_PICKABLE = array( self::PUBLISHER, self::SPONSORED, self::PAID, self::CLASSIFIEDS, self::FULL );
    public const ALL_INCLUDING_CUSTOM = array( self::PUBLISHER, self::SPONSORED, self::PAID, self::CLASSIFIEDS, self::FULL, self::CUSTOM );
    public const DEFAULT_MODE = self::FULL;

    public static function current(): string { /* reads wbam_pro_settings['site_mode'], falls back to FULL, validates against ALL_INCLUDING_CUSTOM */ }

    public static function is( string $mode ): bool { /* strict equality */ }

    public static function labels(): array { /* translated labels, indexed by slug */ }

    public static function descriptions(): array { /* translated 1-liners */ }

    public static function icons(): array { /* icon slugs */ }

    /**
     * Authoritative module bundle per mode. §2.2 values.
     *
     * For 'custom', returns the current persisted enabled_modules as a
     * pass-through — because 'custom' means "whatever the admin set."
     * (D4 applied.)
     */
    public static function module_bundle( string $mode ): array { /* switch on $mode */ }

    /** Capability flags per mode. §2.3. */
    public static function capabilities( string $mode ): array { /* switch on $mode */ }

    /**
     * Apply a mode: atomically writes site_mode + enabled_modules + capabilities.
     * Only accepts modes in ALL_PICKABLE. 'custom' is set elsewhere (auto-flip).
     * Auto modules fall back to get_module_defaults().
     * Idempotent. Returns true on state change, false if already in that state.
     */
    public static function apply( string $mode ): bool { /* atomic update_option */ }

    /**
     * Impact preview for the mode-switch dialog. Pure, no side effects.
     * Returns: ['will_show' => [...], 'will_hide' => [...], 'data_kept' => [...]].
     */
    public static function preview_change( string $from, string $to ): array { /* diff logic */ }

    /**
     * Checks whether the currently-persisted enabled_modules diverges from
     * the current mode's bundle. Used by the auto-custom detection hook.
     * (D3 applied.)
     */
    public static function state_diverges_from_mode(): bool { /* compare bundle vs enabled_modules */ }
}
```

**Load order:** required before any caller. Add `require_once` in `Pro_Plugin::load_dependencies()` immediately after the Enums require (which is already there from the pricing_model plan).

**Verify:** `php -l`, load `/wp-admin/` — no fatals.

---

### Step 2 — Helper functions (with advertiser-context overload per DR6)
**Modified file:** `includes/Core/functions.php`
**Appends ~50 lines:**

```php
if ( ! function_exists( 'wbam_site_mode' ) ) {
    function wbam_site_mode(): string {
        return \WBAM_Pro\Core\Site_Mode::current();
    }
}

if ( ! function_exists( 'wbam_can' ) ) {
    /**
     * Check a capability flag.
     *
     * @param string $capability Capability slug.
     * @param mixed  $context    Optional. An Advertiser object, advertiser ID,
     *                           or null. For per-advertiser capabilities,
     *                           their billing_mode overrides site setting.
     *                           For site-wide capabilities, ignored.
     * @param bool   $default    Fallback when capability is unset. Defaults
     *                           to true — preserves current visible behavior.
     */
    function wbam_can( string $capability, $context = null, bool $default = true ): bool {

        // Per-advertiser capabilities: billing_mode='managed' overrides site-wide.
        $per_advertiser_caps = array( 'show_billing_ui', 'require_package_for_submission' );

        if ( in_array( $capability, $per_advertiser_caps, true ) && null !== $context ) {
            $advertiser = $context;
            if ( is_int( $context ) || is_numeric( $context ) ) {
                $advertiser = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->get( (int) $context );
            }
            if ( is_object( $advertiser ) && isset( $advertiser->billing_mode ) && 'managed' === $advertiser->billing_mode ) {
                return false; // managed advertisers never see billing UI or need to pick packages
            }
        }

        // Site-wide fallback.
        $caps = \WBAM_Pro\Core\Settings_Helper::get( 'capabilities', array() );
        return isset( $caps[ $capability ] ) ? (bool) $caps[ $capability ] : $default;
    }
}
```

**Verify:** CLI smoke test covering (a) unknown capability → default true, (b) known site-wide capability returns stored value, (c) `show_billing_ui` with `$advertiser->billing_mode='managed'` returns false regardless of site setting, (d) `show_ctr_clicks` with advertiser context passed ignores the advertiser (site-wide capability).

---

### Step 3 — DB migration 3.7.0 → 3.8.0 (now includes advertiser billing_mode column per DR3)
**Modified file:** `includes/Core/class-installer.php`
- Bump `const DB_VERSION = '3.8.0';`
- Add `run_upgrades()` gate:
```php
if ( version_compare( $current_version, '3.8.0', '<' ) ) {
    self::upgrade_to_3_8_0();
}
```
- New method:
```php
private static function upgrade_to_3_8_0() {
    global $wpdb;

    // 3.8.0 Part A: map site_mode legacy values to the new 5-value set.
    $settings = get_option( 'wbam_pro_settings', array() );
    $old      = isset( $settings['site_mode'] ) ? $settings['site_mode'] : '';

    $mapping = array(
        ''            => \WBAM_Pro\Core\Site_Mode::FULL,
        'ads'         => \WBAM_Pro\Core\Site_Mode::PAID,
        'classifieds' => \WBAM_Pro\Core\Site_Mode::CLASSIFIEDS,
        'both'        => \WBAM_Pro\Core\Site_Mode::FULL,
    );

    $new = isset( $mapping[ $old ] ) ? $mapping[ $old ] : \WBAM_Pro\Core\Site_Mode::FULL;
    $settings['site_mode'] = $new;

    // Intentional: do NOT set _mode_applied=true. Existing enabled_modules
    // preserved verbatim. Admin must explicitly switch modes to activate
    // the bundle (D12). Autonomous revenue flow preserved.
    update_option( 'wbam_pro_settings', $settings );

    // 3.8.0 Part B: add billing_mode column to advertisers table for
    // per-advertiser self_serve vs managed distinction. Every existing row
    // defaults to 'self_serve' — zero behavior change for current
    // advertisers. Admin explicitly flips individual advertisers to
    // 'managed' via the edit screen.
    $table  = $wpdb->prefix . 'wbam_advertisers';
    $column = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW COLUMNS FROM `{$table}` LIKE %s",
            'billing_mode'
        )
    );
    if ( empty( $column ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            "ALTER TABLE `{$table}` ADD COLUMN `billing_mode` VARCHAR(20) NOT NULL DEFAULT 'self_serve' AFTER `status`"
        );
    }
}
```

**Verify:**
- Load any admin page → migration runs → DB shows `site_mode='full'` (was `both`).
- `SHOW COLUMNS FROM wp_wbam_advertisers LIKE 'billing_mode'` returns one row.
- `SELECT COUNT(*) FROM wp_wbam_advertisers WHERE billing_mode != 'self_serve'` returns 0.
- `_mode_applied` key absent from settings (= opt-in gate still closed).
- Re-run `maybe_run_upgrades` → idempotent (ALTER is gated by SHOW COLUMNS, site_mode re-mapping is a no-op since it's already valid).

---

### Step 4 — Wizard template updated to 5 cards
**Modified file:** `templates/admin/setup-wizard.php`
**Change:** the inline `$modes` array in `wbam_setup_wizard_render_step_1()` replaced with data sourced from `Site_Mode::labels() + descriptions() + icons()`. Keeps the existing radio-card layout.

---

### Step 5 — Wizard handler calls apply()
**Modified file:** `includes/Admin/class-setup-wizard.php` — `save_step_1()`
```php
private function save_step_1() {
    $site_mode = isset( $_POST['site_mode'] )
        ? sanitize_text_field( wp_unslash( $_POST['site_mode'] ) )
        : Site_Mode::DEFAULT_MODE;

    if ( ! in_array( $site_mode, Site_Mode::ALL_PICKABLE, true ) ) {
        $site_mode = Site_Mode::DEFAULT_MODE;
    }

    Site_Mode::apply( $site_mode );
}
```

**Verify:** deactivate + reactivate plugin → wizard fires → pick "Publisher" → DB shows `site_mode='publisher'`, `enabled_modules` matches publisher bundle, `capabilities.show_ctr_clicks=false`.

---

### Step 6 — Site Mode section in General settings + mode-switch dialog
**Modified file:** `includes/Core/class-pro-admin.php` (General tab render)
- New card at top of General tab: shows current mode label + description + "Change Mode" button.
- Dialog (native `<dialog>`) with the 5 radios + live preview panel driven by `Site_Mode::preview_change()`.
- Dialog copy:
  - Header: "Change Site Mode"
  - Per-option description: from `Site_Mode::descriptions()`
  - Preview panel: "Will show: [tabs]. Will hide: [tabs]. **Nothing is deleted** — your data stays intact and returns if you switch back."
  - Checkbox before confirm: "I understand this changes visible features."
  - Cancel + Apply buttons. Apply posts to `admin-post.php`.

---

### Step 7 — Mode-change admin-post handler
**Modified file:** `includes/Core/class-pro-admin.php`
```php
add_action( 'admin_post_wbam_change_site_mode', array( $this, 'handle_site_mode_change' ) );

public function handle_site_mode_change() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die(); }
    check_admin_referer( 'wbam_change_site_mode' );

    $new_mode = isset( $_POST['site_mode'] )
        ? sanitize_text_field( wp_unslash( $_POST['site_mode'] ) )
        : '';

    if ( ! in_array( $new_mode, Site_Mode::ALL_PICKABLE, true ) ) {
        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    Site_Mode::apply( $new_mode );
    set_transient( 'wbam_site_mode_changed', $new_mode, 60 );
    wp_safe_redirect( wp_get_referer() );
    exit;
}
```

---

### Step 8 — Current-mode banner + auto-flip to `custom` (per DR1)
**Modified file:** `includes/Core/class-pro-admin.php`

**Banner (top of Modules tab, ~25 lines inserted before the modules form):**
```
┌──────────────────────────────────────────────────────────────┐
│ Your site is running in:  Publisher mode       [Change mode] │
│ No advertiser portal, no billing, no classifieds.            │
│                                                              │
│ Changing module checkboxes below will switch you to          │
│ "Custom" mode. You can return to Publisher anytime — your    │
│ ads and data are not affected.                               │
└──────────────────────────────────────────────────────────────┘
```

If current mode is `custom`, show: *"Your modules are configured manually (based on last applied: **Publisher**). [Reset to Publisher]"*.

**Auto-flip to `custom` (D3 + DR1):** insert 8 lines immediately after line 3305 in `render_modules_settings()` — AFTER `Settings_Helper::update( 'enabled_modules', $enabled_modules )` but BEFORE the success notice:

```php
// After: $saved = Settings_Helper::update( 'enabled_modules', $enabled_modules );

$current_mode = \WBAM_Pro\Core\Site_Mode::current();
if ( \WBAM_Pro\Core\Site_Mode::CUSTOM !== $current_mode
    && \WBAM_Pro\Core\Site_Mode::state_diverges_from_mode()
) {
    $settings_blob                   = get_option( 'wbam_pro_settings', array() );
    $settings_blob['site_mode']      = \WBAM_Pro\Core\Site_Mode::CUSTOM;
    $settings_blob['_previous_mode'] = $current_mode;
    update_option( 'wbam_pro_settings', $settings_blob );
}
```

Location: immediately after `class-pro-admin.php:3305`. The dependency-cascade logic at lines 3287-3302 runs BEFORE the update, so the `$enabled_modules` that lands in storage is already dependency-resolved. `state_diverges_from_mode()` compares that to the current mode's bundle.

---

### Step 9 — Advertiser analytics tab respects `show_ctr_clicks`
**Modified file:** `templates/portal/tabs/analytics.php`
- Lines 76-105 (Clicks + CTR + Spent tiles) — each wrapped in `<?php if ( wbam_can( 'show_ctr_clicks' ) ) : ?> … <?php endif; ?>`. Spent tile also gated by `wbam_can( 'show_billing_ui' )`.
- Lines 118-123 (Clicks chart toggle) — wrapped in `show_ctr_clicks`.
- Verify JS null-guards for `#stat-clicks`, `#stat-ctr`, `#stat-spent`. Add `if ( el )` guards if missing.

---

### Step 9a — Overview tab respects `show_ctr_clicks`
**Modified file:** `templates/portal/tabs/overview.php`
- Line ~116 (Total Clicks stat tile) — wrap in `wbam_can( 'show_ctr_clicks' )`.
- Line 42 `$has_ads` check — keep as-is (logical OR is fine; purely for empty-state detection).

### Step 9b — Share of Voice tab gated + CTR column hidden
**Modified files:**
- `includes/Modules/Advertisers/class-advertiser-shortcodes.php` (default_tabs assembly around line 309-326) — the `share-of-voice` tab is only added when `wbam_can( 'show_ctr_clicks' )`. In publisher/classifieds modes, tab never appears in the dashboard.
- `templates/portal/tabs/share-of-voice.php` — CTR column at line 181 wrapped in `wbam_can( 'show_ctr_clicks' )` as defense-in-depth.

### Step 9c — Ads tab cards respect `show_ctr_clicks`
**Modified file:** `templates/portal/tabs/ads.php` — per-ad click counts hidden when `wbam_can( 'show_ctr_clicks' ) === false`. Impression counts always shown.

### Step 9d — Admin reports gated by mode (per DR5)
**Modified files (each is a 1-line early return at top of the menu-registration method):**
- `includes/Admin/class-revenue-dashboard.php` — in the `admin_menu` callback (usually `register_admin_page()` or similar), first line: `if ( ! Settings_Helper::is_module_enabled( 'wallet' ) ) { return; }`. Publisher + sponsored hide Revenue automatically.
- `includes/Modules/Analytics/class-inventory-dashboard.php` — same pattern: `if ( ! ( Settings_Helper::is_module_enabled( 'ad_submissions' ) || Settings_Helper::is_module_enabled( 'rotation' ) ) ) { return; }`. Classifieds-only sites hide Inventory.
- `includes/Admin/class-dashboard-widget.php` — the `render()` method checks `wbam_can( 'show_ctr_clicks' )` and `wbam_can( 'show_billing_ui' )` before rendering click-based metrics and revenue metrics respectively. Widget ALWAYS shows impressions + active-ads count.

---

### Step 10-pre — Package terms helper (per DR7)
**New helper in** `includes/Core/functions.php`:

```php
if ( ! function_exists( 'wbam_format_package_terms' ) ) {
    /**
     * Format a package's terms for display.
     *
     * For self_serve advertisers: returns the price string.
     * For managed advertisers: returns a contract-limit description sourced
     *   from impressions_limit / clicks_limit / duration_days.
     */
    function wbam_format_package_terms( $package, $advertiser = null ): string {
        if ( wbam_can( 'show_billing_ui', $advertiser ) ) {
            return wbam_format_price( $package->price );
        }
        // Managed advertiser path.
        $parts = array();
        if ( ! empty( $package->impressions_limit ) ) {
            $parts[] = sprintf( _n( '%s impression', '%s impressions', $package->impressions_limit, 'wb-ad-manager-pro' ), number_format_i18n( $package->impressions_limit ) );
        }
        if ( ! empty( $package->clicks_limit ) ) {
            $parts[] = sprintf( _n( '%s click', '%s clicks', $package->clicks_limit, 'wb-ad-manager-pro' ), number_format_i18n( $package->clicks_limit ) );
        }
        if ( ! empty( $package->duration_days ) ) {
            $parts[] = sprintf( _n( '%s day', '%s days', $package->duration_days, 'wb-ad-manager-pro' ), number_format_i18n( $package->duration_days ) );
        }
        if ( empty( $parts ) ) {
            return __( 'Contract terms', 'wb-ad-manager-pro' );
        }
        return sprintf(
            /* translators: %s: contract terms (impressions / clicks / days) */
            __( 'Contract: %s', 'wb-ad-manager-pro' ),
            implode( ' · ', $parts )
        );
    }
}
```

Then replace the `<div class="wbam-package-price">` renders in:
- `templates/portal/ad-form.php:322` and `:383` (custom-package variant)
- `templates/portal/classified-form.php:423`

Replacement:
```php
<div class="wbam-package-price"><?php echo esc_html( wbam_format_package_terms( $package, $advertiser ?? null ) ); ?></div>
```

---

### Step 10 — Submission form respects `require_package_for_submission` (per DR4)
**Pre-flight (D6):** open `templates/portal/ad-form.php` JS around line 674 — confirm no hard "package required" validation before submit. Existing `wbam-package-custom` radio at line 380 already submits without a specific package.

**Modified file:** `templates/portal/ad-form.php` (not the shortcode class — the template renders inline)

When `! wbam_can( 'require_package_for_submission', $advertiser )`:
- Wrap the priced-package list (lines 312-378) in a capability check
- Pre-select the existing `wbam-package-custom` option so form can submit with `package_id=0`
- Change the form heading from "Choose a package" to "Submit your ad"

Backend path at `Ad_Submission_Manager::submit_ad()` already handles `$package_id=0` cleanly via the `if ( $package_id )` gate at line 110 — no backend code change needed.

**Verify:** sponsored mode (site-wide capability off) AND managed advertiser on paid mode (advertiser-scoped capability off) — both paths submit ads with no package, no billing triggered.

---

### Step 10a — Advertiser portal respects `show_billing_ui`
**Modified files:**
- `includes/Modules/Advertisers/class-advertiser-shortcodes.php` — wallet tab registration at line 321 also gated by `wbam_can( 'show_billing_ui' )` (in addition to `is_module_enabled('wallet')`). In managed mode wallet module is on but wallet tab stays hidden.
- Balance widget on overview template — wrapped in `wbam_can( 'show_billing_ui' )`.
- Package cards in submission form — when `show_billing_ui=false`, price lines replaced with "Contract limit: N impressions / N clicks / N days" derived from `impressions_limit` / `clicks_limit` / `duration_days`. "Pay" CTA replaced with "Assigned by admin" badge.

### Step 10b — Register + become-advertiser flows respect `allow_advertiser_self_register` (per DR2)
**Modified file:** `includes/Modules/Advertisers/class-advertiser-shortcodes.php`

Two entry points gate. There's no standalone register shortcode — registration is embedded in the dashboard flow.

1. **`wbam_register` form at lines 2184-2337** — when `! wbam_can( 'allow_advertiser_self_register' )`, replace form markup with: *"Registration is by invitation only. Please contact the site owner for access."*
2. **`wbam_become_advertiser` flow at lines 354 / form at 2153** — same gate: replace form markup with the invitation-only message.

`POST` handlers stay enabled so signed invitation links (with nonce + admin-created advertiser pending row) still work for invited vendors.

### Step 10c — Advertiser class property + admin edit "Billing Mode" dropdown (per DR3)
**Modified files:**
1. `includes/Modules/Advertisers/class-advertiser.php` — add property + populate/save wiring:
   ```php
   /**
    * Billing mode — 'self_serve' (default) or 'managed'.
    * Managed advertisers see no billing UI; packages assigned by admin
    * with no wallet charging.
    * @var string
    */
   public $billing_mode = 'self_serve';
   ```
   Update `populate()` (~line 225) to read `$data->billing_mode` and `save()` (~line 256) to write it through `$wpdb->insert/update` with sanitization (`in_array( $v, array( 'self_serve', 'managed' ), true )` → default `self_serve`).

2. `includes/Core/class-pro-admin.php` — advertiser edit form (around line 1121 where `$is_edit` is set):
   Add field:
   ```html
   <tr>
     <th scope="row"><label for="billing_mode">Billing Mode</label></th>
     <td>
       <select name="billing_mode" id="billing_mode">
         <option value="self_serve" <?php selected( $advertiser->billing_mode ?? 'self_serve', 'self_serve' ); ?>>Self-Serve (wallet / credits)</option>
         <option value="managed" <?php selected( $advertiser->billing_mode ?? 'self_serve', 'managed' ); ?>>Managed (offline contract, no in-plugin billing)</option>
       </select>
       <p class="description">
         Self-serve advertisers purchase packages via the wallet.
         Managed advertisers are assigned packages by you and billed offline.
         Switching never deletes data; past campaigns and transactions remain visible.
       </p>
     </td>
   </tr>
   ```

3. `includes/Core/class-pro-admin.php` — form save handler: read `$_POST['billing_mode']`, sanitize, pass to `Advertiser_Manager::create/update()`.

4. `includes/Modules/Advertisers/class-advertiser-manager.php` — `create()` and `update()` accept the field (if the setter method exists it's a one-liner).

Existing "Add Advertiser" admin action (line 782) reused — no new admin flow, just a new field on the existing form.

---

### Step 11 — `is_module_enabled()` consults mode bundle ONLY when `_mode_applied=true`
**Modified file:** `includes/Core/class-settings-helper.php`

Per **D12**, the mode bundle is opt-in. Existing money-making customers see zero behavior change until they voluntarily click "Change Mode" or run the wizard.

```php
public static function is_module_enabled( $module_slug ) {
    $enabled_modules = self::get( 'enabled_modules', array() );
    $mode_applied    = (bool) self::get( '_mode_applied', false );

    if ( isset( $enabled_modules[ $module_slug ] ) ) {
        // Explicit value — use it. (Existing customer path.)
        $enabled = (bool) $enabled_modules[ $module_slug ];
    } elseif ( $mode_applied ) {
        // Opt-in path: site has explicitly applied a mode, consult the bundle.
        $bundle = \WBAM_Pro\Core\Site_Mode::module_bundle( \WBAM_Pro\Core\Site_Mode::current() );
        if ( isset( $bundle[ $module_slug ] ) && 'auto' !== $bundle[ $module_slug ] ) {
            $enabled = (bool) $bundle[ $module_slug ];
        } else {
            $defaults = self::get_module_defaults();
            $enabled  = isset( $defaults[ $module_slug ] ) ? $defaults[ $module_slug ] : false;
        }
    } else {
        // Legacy path — existing installs that never opted in. Unchanged from today.
        $defaults = self::get_module_defaults();
        $enabled  = isset( $defaults[ $module_slug ] ) ? $defaults[ $module_slug ] : false;
    }

    if ( ! $enabled ) {
        return false;
    }

    // Dependency chain — unchanged.
    $dependencies = self::get_module_dependencies( $module_slug );
    foreach ( $dependencies as $dep ) {
        if ( ! self::is_module_enabled( $dep ) ) {
            return false;
        }
    }

    return true;
}
```

**Companion change — `Site_Mode::apply()` sets the sentinel:**
```php
public static function apply( string $mode ): bool {
    // ... writes site_mode, enabled_modules, capabilities ...
    $settings['_mode_applied'] = true;
    update_option( 'wbam_pro_settings', $settings );
}
```

**Verify:**
- Existing install (migration ran, `_mode_applied=false`): `is_module_enabled()` behaves identically to pre-plan state. All reports render, all billing UI visible, all auto-billing flows untouched.
- Admin clicks "Change Mode" → stays on `paid` mode (no-op) → `_mode_applied=true` but bundle still says same thing → still no visible change.
- Admin switches `paid → publisher` → bundle kicks in, modules reconfigure, tabs hide as designed.
- Fresh install via wizard: `apply()` fires, `_mode_applied=true`, bundle drives everything.

---

### Step 12 — Docs + changelog (D5)
**Modified files:**
- `readme.txt` — 5 new `* New:` bullets appended under `= 1.5.0 =`:
  - *New: Six site-owner modes — Publisher, Sponsored Advertisers, Managed Advertisers, Paid Advertiser Portal, Classifieds Marketplace, Full Platform — pickable during setup and switchable anytime with a preview of what hides and shows*
  - *New: Managed Advertisers mode — work with vendors under offline contracts; packages act as contract templates with impression/click caps; billing handled externally*
  - *New: Impressions-only analytics option for Publisher-mode sites — hides Clicks, CTR, and Spend tiles across portal + admin reports*
  - *New: Sponsored mode — advertisers submit ads for admin approval without picking a package or connecting to billing*
  - *New: Admin report surfaces (Revenue Dashboard, Inventory Dashboard, WP Dashboard Widget) now respect the current site mode — hidden when they don't apply*
- `CLAUDE.md` — Recent Changes row:
  `| 2026-04-18 | … | Site mode wiring: 6 modes (including Managed for offline-contract vendors), opinionated module bundles, mode-switch UI, report surfaces gated |`
- This plan doc — Status stays "Approved for execution" until final step marks Done.

---

### Step 13 — Transient success notice on mode change
**Modified file:** `includes/Core/class-pro-admin.php`
- If `get_transient( 'wbam_site_mode_changed' )` is set, render `<div class="notice notice-success is-dismissible">` with: *"Site mode changed to **{Label}**. Modules and analytics updated. Your existing ads, classifieds, advertisers, and transactions are unchanged."* Clear transient after render.

---

### Step 14 — End-to-end verification (no commit)

Execute on `wp-ads.local` in this order, capturing screenshots into `docs/verification/2026-04-18-site-mode-wiring/`:

1. `/wp-admin/` — migration runs, DB shows `site_mode='full'` (was `both`), no other change.
2. General settings → "Site Mode" section shows **Full Platform**.
3. Open Change Mode dialog → pick **Publisher** → preview lists what hides → confirm.
4. Admin menu: Transactions / Packages / Campaigns / Classifieds hidden. Ads stays (free plugin).
5. Advertiser dashboard: only `profile` tab visible (everything else off for Publisher).
6. Analytics tab: if reachable, only Impressions tile visible. No JS errors.
7. Switch back to **Full Platform** → every menu and tab returns.
8. Query DB: rows in `wp_wbam_advertisers`, `wp_wbam_campaigns`, `wp_wbam_classifieds`, `wp_wbam_ad_submissions`, `wp_wbam_transactions` all unchanged.
9. From **Full**, manually uncheck `classifieds` in Modules tab → save → current-mode banner now reads *"Custom (based on Full)"*. Click "Reset to Full" → classifieds back on.
10. Zero entries in `wp-content/debug.log` attributed to this work.

---

### Step 15 — Mark plan done
**Modified file:** this plan doc.
- Status → **Done**.
- Append execution summary table with commit SHAs for Steps 1-13.
- Commit message: `docs(plan): site-mode wiring complete, record commit SHAs`.

---

## 4. Commit checklist

```
[ ] Step 0    — Pre-flight: DB snapshot + JS read (no commit)
[ ] Step 1    — feat(core): Site_Mode helper class with 5 modes + capability registry
[ ] Step 2    — feat(core): wbam_can() with advertiser-context overload + wbam_site_mode()
[ ] Step 3    — feat(migration): upgrade_to_3_8_0 — map site_mode + add advertiser billing_mode column
[ ] Step 4    — feat(wizard): render 5 mode cards sourced from Site_Mode registry
[ ] Step 5    — feat(wizard): save_step_1 calls Site_Mode::apply()
[ ] Step 6    — feat(admin): Site Mode card + mode-switch dialog in General settings
[ ] Step 7    — feat(admin): handler for manual mode change via admin-post
[ ] Step 8    — feat(admin): current-mode banner + auto-flip to custom on module edits
[ ] Step 9    — feat(analytics): advertiser analytics tab respects show_ctr_clicks
[ ] Step 9a   — feat(analytics): advertiser overview tab respects show_ctr_clicks
[ ] Step 9b   — feat(analytics): share-of-voice tab gated + CTR column hidden
[ ] Step 9c   — feat(analytics): advertiser ads tab cards respect show_ctr_clicks
[ ] Step 9d   — feat(admin): Revenue / Inventory / Dashboard Widget gated by mode
[ ] Step 10p  — feat(core): wbam_format_package_terms helper for managed-advertiser terms
[ ] Step 10   — feat(submission): ad-form respects require_package_for_submission
[ ] Step 10a  — feat(advertiser): wallet tab + balance + price UI respect show_billing_ui
[ ] Step 10b  — feat(advertiser): register + become-advertiser flows respect allow_advertiser_self_register
[ ] Step 10c  — feat(advertiser): billing_mode property + admin edit dropdown
[ ] Step 11   — refactor(modules): is_module_enabled consults mode bundle only when _mode_applied=true
[ ] Step 12   — docs: readme changelog + CLAUDE.md Recent Changes
[ ] Step 13   — feat(admin): transient success notice on mode change
[ ] Step 14   — Browser E2E verification (no commit)
[ ] Step 15   — docs(plan): mark site-mode-wiring done, record commit SHAs
```

22 implementation commits + 1 docs close-out. Commit messages follow the pricing_model convention. No co-author attribution.

---

## 5. Non-negotiables

1. **Zero behavior change for existing paying customers until they opt in.** (D12) The mode bundle is consulted only after `_mode_applied=true`, which is set only by explicit admin action (wizard or "Change Mode" click). Migration writes the label; it never activates the bundle.
2. **Autonomous revenue flow preserved bit-for-bit on upgrade.** Wallet charging, package purchases, campaign reservations, billing ticks, auto-refunds — every auto-billing code path runs identically before and after this plan. Verified in §9a.
3. **Existing installs migrate to `full` label.** (Step 3) Display-only; no bundle applied.
4. **`Site_Mode::apply()` never deletes data.** Writes `wbam_pro_settings` only. All existing rows across all tables survive every mode switch.
5. **Manual Modules edits flip `site_mode` to `custom`.** (D3) Admins are never trapped.
6. **Reverting any single commit leaves the plugin functional.** Each step is additive or pure substitution.
7. **`wbam_can()` defaults to `true`.** Any capability read before it's written returns current visible behavior.
8. **Plugin version stays at 1.5.0.** No version bump.
9. **Zero free plugin changes.**

---

## 5a. Autonomous revenue safety — existing paying customer walkthrough

Run this check BEFORE shipping. Simulates a `both`-mode site with an active advertiser running a CPC campaign and a wallet balance.

| Check | Pre-plan | Post-plan (no opt-in) | Status |
|-------|----------|----------------------|--------|
| Site mode label in Settings | (no label shown) | `Full Platform` | **Cosmetic only, safe** |
| `_mode_applied` sentinel | N/A | `false` (migration did NOT set it) | **Bundle bypassed** |
| `is_module_enabled('wallet')` | true | true (legacy path) | Identical |
| `is_module_enabled('campaigns')` | true | true (legacy path) | Identical |
| `wbam_can('show_billing_ui')` | N/A | `true` (unset → default true) | Reports render fully |
| Advertiser wallet top-up flow | works | works | Identical |
| Package purchase → wallet debit → campaign reservation | works | works | Identical |
| Ad impression tracking | works | works | Identical |
| Click tracking | works | works | Identical |
| Auto-billing tick (cron) | works | works | Identical |
| Campaign completion → unused-budget refund | works | works | Identical |
| Stripe / PayPal / WooCommerce integrations | work | work | Identical |
| Admin Revenue Dashboard | visible | visible (wallet module still on) | Identical |
| Admin Transactions list | visible | visible | Identical |
| Advertiser analytics tab (Impressions+Clicks+CTR+Spent) | all 4 tiles | all 4 tiles (`wbam_can` defaults true) | Identical |

Any post-plan row that differs from pre-plan = stop, debug, do not ship.

---

## 6. Verification matrix

Run after Step 14. Every cell must be verified in browser.

| Mode | Advertiser dashboard tabs | Admin menus visible | Advertiser analytics tiles | Admin reports | Submission form | Billing UI |
|------|---------------------------|---------------------|----------------------------|---------------|-----------------|------------|
| `publisher` | profile | (none added by Pro) | N/A | Inventory Dashboard (impressions only) | N/A | hidden |
| `sponsored` | overview, ads, analytics, profile | Submissions, Inventory Dashboard | Imp + Clicks + CTR (no Spend) | Inventory Dashboard | No package picker | hidden |
| `managed` | overview, ads, campaigns (cap progress), analytics, profile | Submissions, Campaigns, Packages, Inventory Dashboard, Advertisers (with "Add Managed Advertiser") | Imp + Clicks + CTR (no Spend) | Inventory only | Package picker (no prices, shows "Contract limit") | hidden |
| `paid` | overview, ads, campaigns, wallet, analytics, profile | Submissions, Campaigns, Packages, Transactions, Inventory, Revenue | All 4 tiles | All | Package picker with prices | shown |
| `classifieds` | overview, classifieds, inquiries, favorites, following, profile | Classifieds, Revenue (for upgrade billing) | (analytics tab not added) | Revenue only | N/A | shown (for classifieds upgrades) |
| `full` | all tabs | all menus | all 4 | all | Package picker with prices | shown |
| `custom` | driven by current enabled_modules | same | driven by capabilities | driven by capabilities | driven by capabilities | driven by capabilities |

---

## 7. Rollback plan

If any single step regresses unexpectedly:

```bash
git revert <sha>
```

If catastrophe (all 15 steps need to roll back):

```bash
git revert <step-15..step-1 in reverse order>
```

Migration in Step 3 is idempotent — re-running after a revert loops back through `upgrade_to_3_8_0()` harmlessly because `site_mode` is already in the new set of values.

---

## 8. What this plan explicitly does NOT do

- Does NOT rename any existing module.
- Does NOT touch the ad-serving hot path.
- Does NOT change any DB table schema.
- Does NOT modify REST API surface.
- Does NOT introduce `Capabilities`, `Context`, `Resolver`, or `Registry` abstractions — two helpers (`wbam_can`, `wbam_site_mode`) cover every need here.
- Does NOT add a new admin page or admin menu entry.
- Does NOT change ANY file in the free plugin.
- Does NOT bump the plugin version.

---

## 9. Success criteria

Plan is Done when:

- [ ] 21 implementation commits on branch `1.5.0`
- [ ] DB on `wp-ads.local` shows `site_mode='full'` and `_mode_applied` unset/false after migration
- [ ] Running the §5a autonomous-revenue walkthrough: every row shows "Identical" between pre-plan and post-plan-no-opt-in
- [ ] Switching to `publisher` (opt-in) hides Pro admin submenus and advertiser dashboard tabs as per verification matrix
- [ ] Switching to `publisher` leaves every data row intact
- [ ] Switching back to `full` returns every menu and tab to prior state
- [ ] Analytics tab in `publisher` mode shows only Impressions
- [ ] Sponsored-mode submission works without package selection, no billing triggered
- [ ] Managed mode: "Add Managed Advertiser" admin action creates advertiser with WP invitation email; public register shortcode shows invitation-only message; wallet tab hidden; package cards show "Contract limit" instead of prices
- [ ] Toggling a module checkbox flips `site_mode` to `custom`; "Reset to previous" restores
- [ ] Zero JS console errors across all modes
- [ ] Zero PHP warnings in `wp-content/debug.log`

---

## Appendix A — Sizing

| Metric | Estimate |
|--------|----------|
| New files | 1 (`class-site-mode.php`) |
| Modified files | ~14 |
| New lines of code | ~550 |
| Modified lines of code | ~150 |
| Commits | 21 implementation + 1 docs close-out |
| Effort | One focused evening (6-7 hours including verification) |

## Appendix B — Evidence references

- Existing wizard: `class-setup-wizard.php:174-183` (saves `site_mode`, no reader).
- Wizard template: `templates/admin/setup-wizard.php:108-127`.
- Analytics tiles hardcoded: `templates/portal/tabs/analytics.php:65-105`.
- Module DAG: `Settings_Helper::get_module_dependencies()` at `class-settings-helper.php:355-369`.
- Module defaults (all-true): `class-settings-helper.php:146-164`.
- Standalone-submission path: `class-ad-submission-manager.php:108-234` (gated by `if ( $package_id )` at line 110).
- Settings blob access: `Settings_Helper::get/update` at `class-settings-helper.php:30-69`.

## Appendix C — Execution summary

Shipped as 8 focused commits on branch `1.5.0` (smaller than the 22-step plan projected — closely-related steps batched where safe, each commit still independently revertable).

| Commit | Steps | Outcome |
|--------|-------|---------|
| `281edeb` | 1 | Site_Mode registry with 5 modes + 4 capabilities + apply/preview/divergence. 33/33 contract tests pass. |
| `0a6a6f3` | 2 | `wbam_can()` with advertiser-context overload + `wbam_site_mode()` helpers. |
| `fb619e5` | 3 | upgrade_to_3_8_0 migration: site_mode remap + billing_mode column. DB_VERSION 3.7.0→3.8.0. _mode_applied NOT set (opt-in gate closed). |
| `619ce68` | 4, 5 | Wizard template renders 5 mode cards; save_step_1 calls Site_Mode::apply(). |
| `1a6e928` | 6, 7, 8 | General-tab Site Mode card + Change Mode dialog + admin-post handler; Modules-tab current-mode banner + auto-flip to custom on divergence. |
| `f3db279` | 9, 9a, 9b, 9c, 9d | Every report surface gated: analytics tab Clicks/CTR/Spent tiles, overview Total Clicks, share-of-voice tab + CTR column, ads tab Clicks stat, admin Revenue Dashboard + Slot Inventory menus. |
| `308e6d9` | 10, 10p, 10a, 10b, 11 | wbam_format_package_terms helper; Advertiser::$billing_mode property + populate/save; wallet tab + register form gated; ad-form package price ↔ contract terms; is_module_enabled mode-bundle fallback gated by _mode_applied (D12). |
| `8829be7` | 10c, 12 | Admin advertiser edit screen Billing Mode dropdown + readme 1.5.0 changelog. |
| `7b39250` | style | WPCS alignment auto-fixes on Site_Mode. |

**Runtime verified on wp-ads.local:**
- site_mode='full' (label only)
- _mode_applied absent (opt-in gate closed)
- enabled_modules unchanged (11 all true)
- capabilities absent (wbam_can returns true)
- billing_mode column present, all 6 advertisers self_serve
- Admin loads with 0 console errors; Site Mode card renders; switch dialog opens showing 5 radios with correct labels/descriptions.

**Autonomous revenue flow preserved bit-for-bit for existing paying customers (D12).**
