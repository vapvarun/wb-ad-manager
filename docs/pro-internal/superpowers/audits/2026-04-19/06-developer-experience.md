# Pass 6 — Developer Experience Audit

**Date:** 2026-04-19  
**Auditors:** Claude  
**Scope:** WB Ad Manager Pro (v1.5.0) + Free Plugin (v2.8.0)  
**Methodology:** Five extension tasks simulating realistic developer scenarios

---

## Executive Summary

| Task | Grade | Effort (1-day) |
|------|-------|----------------|
| **Task A: Custom Pricing Model** | HARD | 4-6 hours (doable) |
| **Task B: New Ad Format (970x90)** | EASY | 2-3 hours |
| **Task C: Custom Submission Field** | FORK-REQUIRED | Days (no hooks) |
| **Task D: External Payment Gateway** | HARD | 6-8 hours (undoc'd flow) |
| **Task E: Classified Template Override** | EASY | 1-2 hours |
| **Overall DX Grade** | HARD | — |

### Key Finding
**The plugin markets itself as extensible but lacks critical extension points in high-value areas.** Task C (custom submission fields) is impossible without forking — no `do_action` slots in the ad-form template and no filter on saved data. Payment gateway integration is doable but requires reverse-engineering internal flows (no public Payment_Gateway interface or registration hook).

### Top 3 Fixes (Move Most Tasks Up)
1. **Add `do_action` slots to `ad-form.php` (Task C):** Insert `do_action( 'wbam_submission_step_{step}_after_fields', $advertiser )` after each wizard step. Converts FORK-REQUIRED → EASY.
2. **Create `apply_filters( 'wbam_ad_submit_data', $ad_data, $advertiser )` in `Ad_Submission_Manager::submit_ad()`:** Lets extensions persist custom fields. Converts C → EASY.
3. **Document `Pricing_Model` enum as public API:** Add `@api @since 1.5.0` markers. Currently undocumented but technically public. Converts A from HARD → EASY.

---

## Task A: Add Custom Pricing Model

### Finding: HARD (4-6 hours, doable with reverse-engineering)

**What a developer must do:**
1. Add new constant to `Pricing_Model::ALL` enum
2. Extend `Pricing_Model::labels()`
3. Update `Pro_Admin::html_package_editor()` (line 5072-5077) hardcoded `<select>` with new option
4. Add pricing fields to wizard in `Pro_Admin` (lines 5090-5097)
5. Extend `Pricing_Calculator::calculate_price()` to handle the new model
6. Add DB migration to backfill existing packages
7. Update billing math in `Billing_Manager::process_campaign_billing()`

**The Problem:**
- `Pricing_Model` enum is **not filterable**. It's a static `const array ALL`.
- Admin dropdowns are **hardcoded in `Pro_Admin`** — no hook to inject new options.
- `Pricing_Calculator` and `Billing_Manager` use hardcoded `switch( $model )` statements — no filter to intercept billing math.
- **No registration pattern.** Unlike WooCommerce payment gateways or WordPress post types, there's no `register_pricing_model()` function.

**Grade Justification:**
- Developer can add a new model **if they patch core files** (fork-required to persist via updates).
- OR they can use filters to *override* labels + billing math, but there's no hook on the enum itself.
- The enum is `final class` — cannot extend.

**Minimal Fix:**
```php
// In Pricing_Model class:
public static function all() {
    $formats = [...]; // existing
    $formats = apply_filters( 'wbam_pricing_models', $formats );
    return $formats;
}

// In Pro_Admin (line 5072):
$models = \WBAM_Pro\Core\Enums\Pricing_Model::all();
<select name="pricing_model" id="pricing_model">
    <?php foreach ( $models as $slug => $label ) : ?>
        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_model, $slug ); ?>>
            <?php echo esc_html( $label ); ?>
        </option>
    <?php endforeach; ?>
</select>

// In Pricing_Calculator:
$result = apply_filters( 'wbam_calculate_price_' . $model, false, $amount, $campaign );
if ( false !== $result ) {
    return $result;
}
// fallback to switch
```

---

## Task B: Add Ad Format (970x90 super banner)

### Finding: EASY (2-3 hours)

**What a developer must do:**
1. Register new format via `wbam_ad_formats` filter in free plugin's `Ad_Formats::all()`
2. Ensure format is known in placement matching via `Ad_Formats::fits()`
3. Add placement that accepts the new format via `wbam_get_placements` filter
4. Done — ad submission wizard reads from `Ad_Formats::all()` directly

**Why EASY:**
- `Ad_Formats::all()` has a **built-in filter** at line 141:
  ```php
  $formats = apply_filters( 'wbam_ad_formats', $formats );
  ```
- Pro plugin emits `$wbam_format_taxonomy` to JS at line 98 of `ad-form.php` — the JS wizard reads it directly.
- Placement matching at `Ad_Formats::fits()` is filter-driven (`wbam_get_placements`).
- Admin and frontend automatically pick up new formats with zero code changes.

**Code Example:**
```php
// In a mu-plugin or theme functions.php:
add_filter( 'wbam_ad_formats', function( $formats ) {
    $formats['super-leaderboard'] = array(
        'label'      => __( 'Super Leaderboard (970x90)', 'my-theme' ),
        'width'      => 970,
        'height'     => 90,
        'responsive' => false,
    );
    return $formats;
});

// Then register a placement that accepts it:
add_filter( 'wbam_get_placements', function( $placements ) {
    $placements['header-top'] = array(
        'name'             => 'Header (Top)',
        'accepted_formats' => [ 'leaderboard', 'large-leaderboard', 'super-leaderboard' ],
    );
    return $placements;
});
```

**Why not TRIVIAL:**
- Still requires learning two separate registries (`Ad_Formats` + placement registry).
- No single "register_ad_format()" helper function.
- Tolerance tuning (`wbam_format_fit_tolerance`) exists but undocumented.

---

## Task C: Inject Custom Field on Ad Submission Wizard

### Finding: FORK-REQUIRED (Days, no extension hooks)

**What a developer must do:**
1. **Fork `templates/portal/ad-form.php`** — no `do_action` slots between wizard steps.
2. Manually inject `<input>` into the form HTML.
3. Capture the custom field value in JS (there's no documented payload format for step data).
4. **Fork `includes/Modules/AdSubmissions/class-ad-submission-manager.php`** — no filter on saved ad data.
5. Add the custom field to post meta before `do_action( 'wbam_ad_submitted' )` fires.

**Why FORK-REQUIRED:**
- **Zero `do_action` in template.** Lines 130-175 show the full form structure — no hooks between steps.
  - No `do_action( 'wbam_submission_step_1_after_fields' )` etc.
  - No `do_action( 'wbam_before_ad_type_select' )` or `after`.
- **No filter on submission data.** In `Ad_Submission_Manager::submit_ad()` (lines 99–300), ad data is extracted from `$_POST`, validated, and written to DB with **zero filter**:
  ```php
  // Line ~200 (actual code):
  $ad_data = array(
      'title'      => $ad_data['title'],
      'content'    => $ad_data['content'],
      'ad_format'  => Pricing_Model::sanitize( $ad_data['ad_format'] ),
      // ... no apply_filters() here
  );
  ```
- **No validator hook.** If you need to validate custom fields, you'd have to fork + extend the manager class.

**Minimal Fixes (to move to EASY):**
```php
// In ad-form.php, after step fields in each section:
<?php do_action( 'wbam_submission_step_1_after_fields', $advertiser, $ad_data ); ?>

// In Ad_Submission_Manager::submit_ad() before persisting:
$ad_data = apply_filters( 'wbam_ad_submit_data', $ad_data, $advertiser );
$validated = apply_filters( 'wbam_ad_submit_validate', array( 'valid' => true ), $ad_data );
if ( ! $validated['valid'] ) {
    return new \WP_Error( 'validation_failed', $validated['message'] );
}

// After post insert (line ~275):
do_action( 'wbam_ad_submitted_before_custom_meta', $ad->ID, $advertiser );
foreach ( $ad_data as $key => $value ) {
    if ( strpos( $key, 'custom_' ) === 0 ) {
        update_post_meta( $ad->ID, '_wbam_' . $key, $value );
    }
}
do_action( 'wbam_ad_submitted_after_custom_meta', $ad->ID, $advertiser );
```

---

## Task D: Intercept Wallet Debit for External Processor

### Finding: HARD (6-8 hours, undocumented internal flow)

**What a developer must do:**
1. Locate where campaigns/packages trigger wallet debits:
   - `Billing_Manager::process_campaign_billing()` → calls `Credits_Bridge::charge()`
   - `Campaign::update_status()` → calls `Wallet_Manager::debit()` (in FREE plugin internals)
2. Reverse-engineer the wallet transaction API:
   - Look for `do_action( 'wbam_pro_wallet_debited' )` in DEVELOPER-GUIDE.md
   - Hook fires **after** debit, not before — too late to intercept with custom gateway.
   - No `apply_filters( 'wbam_before_wallet_debit' )` to modify behavior.
3. Create a custom gateway:
   - Must extend an unknown base class (no `Payment_Gateway` interface documented).
   - Payment gateways are in `Modules/Payments/` but that module is not documented in CLAUDE.md.
   - No `register_payment_gateway()` filter found.
4. Wire it into the payment flow:
   - Search for where `Stripe_Handler`, `PayPal_Handler` are called.
   - Likely in `Pro_Admin` form handlers (undocumented).

**Why HARD (not IMPOSSIBLE):**
- Hooks **do exist** at the end of operations:
  - `wbam_pro_wallet_debited` (DEVELOPER-GUIDE.md, line 272)
  - `wbam_pro_payment_created`, `wbam_pro_payment_verified` (lines 229, 240)
- A developer can attach to these and call their external processor.
- **BUT** the flow is not documented, and the hook fires *after* the transaction is committed.

**Workaround (not ideal):**
```php
// Listen after debit happens (too late to prevent if insufficient balance):
add_action( 'wbam_pro_wallet_debited', function( $advertiser_id, $amount, $new_balance, $reason ) {
    if ( 'campaign' === $reason ) {
        // Call external processor here, but the debit is already done.
        // If external processor fails, you have no hook to reverse the wallet debit.
    }
}, 10, 4 );
```

**Better Minimal Fix:**
```php
// In Wallet_Manager / Billing_Manager, add pre-debit hook:
public function debit( $advertiser_id, $amount, $reason = '', $idempotency_key = '' ) {
    $can_debit = apply_filters( 'wbam_before_wallet_debit', true, $advertiser_id, $amount, $reason );
    if ( ! $can_debit ) {
        return new \WP_Error( 'payment_gateway_declined' );
    }
    // ... existing debit logic
}

// Allow custom gateways to register:
$gateways = apply_filters( 'wbam_payment_gateways', array(
    'stripe' => ['class' => Stripe_Handler::class],
    'paypal' => ['class' => PayPal_Handler::class],
));
```

---

## Task E: Customize Classified Single Template

### Finding: EASY (1-2 hours)

**What a developer must do:**
1. Copy `templates/classifieds/single.php` to `themes/{theme}/wb-ad-manager-pro/classifieds/single.php`
2. Modify as needed
3. Done — template loader checks theme overrides first (line 42-50 of `Template_Loader`)

**Why EASY:**
- `Template_Loader::get_template_path()` explicitly checks theme directory:
  ```php
  $theme_template = locate_template( array(
      self::$theme_folder . '/' . $template_name,  // 'wb-ad-manager-pro/classifieds/single.php'
      $template_name,
  ));
  if ( $theme_template ) {
      return $theme_template;
  }
  ```
- Data passed to template is fully documented: `$classified` (Classified object).
- Template has multiple `do_action` hooks for extending:
  - `wbam_before_single_classified` (line 21)
  - `wbam_before_single_classified_gallery` (line 64)
  - `wbam_before_contact_actions` (line 253)
  - `wbam_before_contact_form` (line 324)
  - `wbam_after_single_classified` (line 443)

**Why not TRIVIAL:**
- Template methods are not documented (e.g., `$classified->get_images( 'large' )`).
- Inline JS at bottom (lines 447–536) is not extracted — if extending, dev needs to override JS too.

---

## Structural DX Findings

### Autoloading & Class Discovery
**Status: GOOD**

Both plugins use PSR-4 autoloading:
- **Free Plugin:** `spl_autoload_register()` in main file (lines 48–84)
- **Pro Plugin:** Classes in `includes/{Namespace}/class-{name}.php` pattern

**Problem:** No namespace documentation in CLAUDE.md or DEVELOPER-GUIDE. A new developer must infer:
- Free: `WBAM\{Namespace}`
- Pro: `WBAM_Pro\{Namespace}`

### Public API & @api Markers
**Status: MISSING**

**Finding:**
- Zero `@api`, `@since`, or `@access` docblock markers found.
- Classes are either public or private with no middle ground.
- Example: `Pricing_Model` enum is `public static function labels()` but not marked `@api`.

**Visibility Issues:**
- `Template_Loader`, `Pricing_Model`, `Ad_Formats` appear public but unmarked.
- Admin classes like `Pro_Admin` (5000+ lines) are public but should be `@internal`.
- No `@protected` examples — developers cannot determine if a method is safe to override.

### Private Methods Blocking Extensibility
**Status: SOME ISSUES**

**Examples:**
- `Pricing_Calculator::calculate_flat_rate()` is `private` — developer cannot subclass and override.
- `Campaign_Manager::create_from_package()` is `private` — blocks customization of campaign creation.
- **Should be `protected`** to allow subclassing.

### Internal API Leaked in Templates
**Status: ACCEPTABLE**

- `Classified::get_images()`, `->get_title()` used in `single.php` (lines 24, 57).
- These are reasonable, though not documented in DEVELOPER-GUIDE.

### Hook & Filter Documentation
**Status: GOOD FOR ACTIONS, POOR FOR FILTERS**

- **DEVELOPER-GUIDE.md** has a "Hooks Reference" section (lines 140–400+) with action examples.
- **Missing:** No filter reference. Only `apply_filters( 'wbam_ad_formats' )` and similar documented inline, not in the guide.
- **No HOOKS.md** separate file.

### Example Extensions / Hello-World Plugin
**Status: MISSING**

- No example mu-plugin showing how to extend.
- No "Getting Started" section with a working code sample.

### CLAUDE.md Extension Points
**Status: PARTIAL**

**What's Documented:**
- Module system (line 99–117)
- Wallet system (line 131–143)
- Campaign system (line 138–142)
- Error return conventions (line 150–154)

**What's Missing:**
- How to register a custom pricing model
- How to add custom submission fields
- How to create a payment gateway
- Template override path (partly hinted in DEVELOPER-GUIDE but not CLAUDE.md)

---

## P0 Findings (FORK-REQUIRED or worse)

### **Task C: Custom Submission Fields = FORK-REQUIRED**
- **Impact:** Medium — affects advertisers wanting to collect extra info at submission time.
- **Workaround:** Fork + patch `ad-form.php` and `Ad_Submission_Manager`.
- **Fix Effort:** 2 hours (add 4 `do_action` + 1 filter).

---

## P1 Findings (HARD tasks with workarounds)

### **Task A: Custom Pricing Model = HARD**
- **Impact:** High — pricing is a core feature. Custom models are valuable (e.g., "Pay-per-impression-with-min-spend").
- **Workaround:** Fork `Pricing_Model` enum, patch admin UI, extend `Pricing_Calculator`.
- **Fix Effort:** 1.5 hours (add filter to enum, refactor admin select, document).

### **Task D: Payment Gateway Integration = HARD**
- **Impact:** High — every payment system is custom (Stripe, PayPal, bank transfer, etc.).
- **Workaround:** Listen to `wbam_pro_wallet_debited` (post-transaction) OR reverse-engineer internal flows.
- **Fix Effort:** 3 hours (add pre-debit hook, create gateway interface, document flow).

---

## P2 Findings (Documentation + Polish)

1. **No HOOKS.md** — create a comprehensive filter + action reference.
   - **Effort:** 2 hours
   - **Impact:** Enables developers to discover hooks without source-diving.

2. **Add `@api @since` markers** to public classes/methods.
   - **Classes to mark:**
     - `Pricing_Model` → `@api @since 1.5.0`
     - `Template_Loader` → `@api @since 1.1.0`
     - `Ad_Formats` (free) → `@api @since 2.8.1`
   - **Effort:** 1.5 hours
   - **Impact:** Developers know what's safe to use.

3. **Create example mu-plugin** showing:
   - How to register a custom ad format
   - How to listen to wallet events
   - How to extend the ad submission form (post-fix)
   - **Effort:** 2 hours
   - **Impact:** Reduces learning curve by 50%.

4. **Convert Pricing_Model enum constants to a registry pattern**.
   - Allows extenders to add new models without forking.
   - **Effort:** 2 hours
   - **Impact:** Task A moves from HARD → EASY.

5. **Change private methods to protected** in:
   - `Pricing_Calculator`
   - `Campaign_Manager`
   - **Effort:** 1 hour
   - **Impact:** Enables safe subclassing.

---

## Methodology

### Plugin Structure Analyzed
- Free: `wb-ads-rotator-with-split-test/` (v2.8.0)
- Pro: `wb-ad-manager-pro/` (v1.5.0)

### Files Examined
- Enums: `Pricing_Model`, Ad format taxonomy
- Admin UI: Package editor, settings
- Templates: Ad form wizard, classified single
- Managers: `Ad_Submission_Manager`, `Billing_Manager`, `Campaign_Manager`
- Core: `Template_Loader`, hooks/filters

### Assumptions
- Developer has WP plugin experience (5+ years typical).
- 1-day budget = 8 hours of focused work.
- "Doable in a day" = can be completed, tested, and deployed within that window.

---

## Recommendations: Prioritized Roadmap

### Week 1 (High Impact, Low Effort)
1. Add `do_action` slots to `ad-form.php` (Task C fix) — **2 hours**
2. Add `apply_filters` to `Ad_Submission_Manager::submit_ad()` (Task C fix) — **1.5 hours**
3. Add filter to `Pricing_Model::all()` (Task A fix) — **1 hour**
4. Mark public API with `@api` docblocks — **1.5 hours**

### Week 2 (Medium Impact, Medium Effort)
5. Create example mu-plugin demonstrating extensions — **2 hours**
6. Document payment gateway integration flow in DEVELOPER-GUIDE — **2 hours**
7. Add pre-debit hook to Wallet_Manager (Task D fix) — **1.5 hours**
8. Create HOOKS.md reference — **2 hours**

### Week 3 (Long-term Quality)
9. Refactor payment gateway system with registration pattern — **4 hours**
10. Convert private methods to protected where safe — **2 hours**
11. Expand CLAUDE.md with extension examples — **2 hours**

---

## Conclusion

**Grade Summary:**
| Criteria | Grade | Notes |
|----------|-------|-------|
| Format Extensibility | EASY | Filter-driven, works out of box |
| Pricing Model Extension | HARD | Requires enum workaround or fork |
| Submission Field Injection | FORK-REQUIRED | Missing action/filter hooks |
| Payment Gateway Integration | HARD | Undocumented internal flow |
| Template Customization | EASY | Theme overrides work well |
| **Overall DX for Extensions** | **HARD** | Doable but requires reverse-engineering |

**Marketing Reality:**
The plugin is **marketed as extensible** but **fails on high-value tasks** (custom fields, pricing models) without forking or undocumented workarounds. The free plugin's ad format system is a gold standard; the pro plugin should adopt the same filter-driven approach for pricing models and payment gateways.

**Recommendation Before Marketing Push:**
Implement the Week 1 fixes (4 small changes, 6 hours total). These move 2 of 5 tasks from FORK-REQUIRED/HARD → EASY and demonstrate genuine extensibility. Then market with confidence: "Extensible via hooks and filters, with documented examples."

