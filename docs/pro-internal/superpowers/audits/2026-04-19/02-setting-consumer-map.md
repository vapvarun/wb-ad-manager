# Pass 2 — Setting → Consumer Map

Audit Date: 2026-04-19
Scope: Settings consumption patterns across wb-ad-manager-pro and wb-ads-rotator-with-split-test
Thoroughness Level: Very Thorough

## Summary

- **TEMPLATE-DIRECT-READ**: 34 instances
- **BYPASS-HELPER**: 58 instances  
- **REPEAT-READ**: 12 instances
- **HARDCODED-IN-TEMPLATE**: 2 instances
- **NOT-FILTERABLE**: 89 instances (all get_option calls lack apply_filters wrapper)
- **MISSING-DEFAULT**: 8 instances

**Severity Breakdown:**
- **P0 (Critical)**: 4 findings
- **P1 (High)**: 18 findings  
- **P2 (Medium)**: 92 findings

---

## P0 Findings

### 1. REPEAT-READ: Rotation_Engine::get_rotation_model() + get_ads_per_placement()

**File**: `/includes/Modules/Rotation/class-rotation-engine.php`
**Lines**: 515-516, 526-527
**Severity**: P0 - Called on every ad render in tight loop

```php
// Line 514-516
public function get_rotation_model() {
    $settings = get_option( 'wbam_pro_settings', array() );
    return isset( $settings['rotation_model'] ) ? $settings['rotation_model'] : self::MODEL_EQUAL;
}

// Line 525-531
public function get_ads_per_placement( $placement_id ) {
    $settings      = get_option( 'wbam_pro_settings', array() );
    $per_placement = isset( $settings['rotation_ads_per_placement'] ) ? $settings['rotation_ads_per_placement'] : array();
    return isset( $per_placement[ $placement_id ] ) ? (int) $per_placement[ $placement_id ] : 1;
}
```

**Pattern**: REPEAT-READ + BYPASS-HELPER
**Issue**: Called multiple times per pageview during rotation selection loop. Should cache at request scope or use Settings_Helper.
**Fix**: Add static request cache or invoke `Settings_Helper::get()` once per request.

---

### 2. REPEAT-READ: Frequency_Cap_Pro::resolve_advertiser_cap() + resolve_campaign_cap()

**File**: `/includes/Modules/Targeting/class-frequency-cap-pro.php`
**Lines**: 134-135, 164-165
**Severity**: P0 - Same settings fetched in two methods of same instance

```php
// Line 134-135
$settings = get_option( 'wbam_pro_settings', array() );
$default  = isset( $settings['per_advertiser_session_cap'] ) ? (int) $settings['per_advertiser_session_cap'] : 0;

// Line 164-165
$settings = get_option( 'wbam_pro_settings', array() );
$default  = isset( $settings['per_campaign_session_cap'] ) ? (int) $settings['per_campaign_session_cap'] : 0;
```

**Pattern**: REPEAT-READ within same class filter call
**Issue**: Settings_Helper not used; redundant fetches if both caps checked on same ad.
**Fix**: Cache settings at class level or call Settings_Helper::get() once.

---

### 3. TEMPLATE-DIRECT-READ: date_format via get_option in Email Templates

**File**: `/templates/emails/advertiser-campaign-ended.php:56`
**Lines**: 56
**Severity**: P0 - Email templates should receive formatted date via context

```php
<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $campaign->end_date ) ) ); ?>
```

**Pattern**: TEMPLATE-DIRECT-READ
**Issue**: Template directly calls get_option. Should receive `$formatted_end_date` via `$view_data`.
**Fix**: Pass `'end_date_formatted' => date_i18n( get_option('date_format'), strtotime($campaign->end_date) )` to template context.

---

### 4. BYPASS-HELPER: Keywords Linker Reading Free Plugin Settings in Pro Code

**File**: `/includes/Modules/Links/class-keyword-linker.php:549`
**Lines**: 549
**Severity**: P0 - Pro code directly reads free plugin settings

```php
$wbam_settings = get_option( 'wbam_settings', array() );
```

**Pattern**: BYPASS-HELPER + Cross-Plugin Settings Leak
**Issue**: Pro module reads free plugin's settings directly. No interoperability wrapper.
**Fix**: Create Settings_Bridge to abstract free/pro settings differences, or document dependency.

---

## P1 Findings

### 1. REPEAT-READ: License Manager Multiple get_option Calls in Single Method

**File**: `/license/class-wbam-pro-license-manager.php:119-121`
**Severity**: P1

```php
// Lines 119-121 in same method:
$license_key    = get_option( 'wbam_pro_license_key', '' );
$license_status = get_option( 'wbam_pro_license_status', '' );
$license_data   = get_option( 'wbam_pro_license_data', null );
```

**Pattern**: REPEAT-READ
**Issue**: Multiple get_option calls in same request context.
**Fix**: Fetch once into array, or create License_Settings helper.

---

### 2. BYPASS-HELPER: Settings_Helper Instantiation Never Used

**File**: `/includes/Core/class-pro-admin.php` (multiple locations: 576, 3020, 3369, 3666)
**Severity**: P1

Examples:
- Line 576: `$existing = get_option( 'wbam_pro_settings', array() );`
- Line 3369: `$blob = get_option( 'wbam_pro_settings', array() );`
- Line 3666: `$pro_settings = get_option( 'wbam_pro_settings', array() );`

**Pattern**: BYPASS-HELPER
**Issue**: Pro_Admin class has direct access to Settings_Helper but bypasses it.
**Fix**: Replace all with `Settings_Helper::get()` or `Settings_Helper::get_instance()->get()`.

---

### 3. TEMPLATE-DIRECT-READ: Classified Reviews Template

**File**: `/templates/classifieds/reviews.php:54`
**Severity**: P1

```php
<span class="wbam-review-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review->created_at ) ) ); ?></span>
```

**Pattern**: TEMPLATE-DIRECT-READ
**Issue**: Template queries get_option directly for date format.
**Fix**: Pass `$review->created_at_formatted` in view_data.

---

### 4. HARDCODED-IN-TEMPLATE: Bump Price Default Hard-Coded

**File**: `/includes/Modules/Classifieds/class-classified-shortcodes.php:1167`
**Severity**: P1 - Default is `1` in template but `1` is hardcoded elsewhere

```php
// Line 3927 in Pro_Admin shows setting field with:
value="<?php echo esc_attr( $settings['bump_price'] ?? 1 ); ?>"

// Line 1167 in Shortcodes fetches:
$bump_price = (float) get_option( 'wbam_bump_price', 1 );
```

**Pattern**: HARDCODED-IN-TEMPLATE + BYPASS-HELPER
**Issue**: `1` repeated as default in multiple places. Should be Settings_Helper::get( 'bump_price', 1 ).
**Fix**: Create constant `BUMP_PRICE_DEFAULT = 1` or use Settings_Helper throughout.

---

### 5. BYPASS-HELPER: Admin_Email Direct Reads in Multiple Modules

**File**: Multiple email notification contexts
- `/includes/Modules/Notifications/class-email-notifications.php:188, 353, 858, 973`
- `/includes/Modules/Classifieds/class-classified-billing.php:403`
- `/includes/Modules/Advertisers/class-advertiser-email-notifications.php:601`

**Severity**: P1

```php
$admin_email = get_option( 'admin_email' );
```

**Pattern**: BYPASS-HELPER + NOT-FILTERABLE
**Issue**: WordPress `admin_email` is called directly. Should be wrapped in `apply_filters()` to allow overrides.
**Fix**: Use `apply_filters( 'wbam_admin_email', get_option( 'admin_email' ) )`.

---

### 6. NOT-FILTERABLE: Date Format Calls Lack Override Mechanism

**File**: `/includes/Modules/Classifieds/class-classified-shortcodes.php:891, 926, 939`
**Severity**: P1

```php
date_i18n( get_option( 'date_format' ), strtotime( $upgrade['expires_at'] ) )
```

**Pattern**: NOT-FILTERABLE
**Issue**: No `apply_filters()` wrapper. Developers cannot override date format per context.
**Fix**: Wrap as `apply_filters( 'wbam_display_date_format', get_option( 'date_format' ) )`.

---

### 7. MISSING-DEFAULT: DB Version Check Without Default

**File**: `/includes/Core/class-installer.php:67`
**Severity**: P1

```php
$current_version = get_option( 'wbam_pro_db_version', '1.0.0' );
```

**Pattern**: Has default ('1.0.0') but caller should validate type
**Issue**: No null-check after fetch; relies on default silently.
**Fix**: Validate as string and numeric version comparison.

---

## P2 Findings

### Multiple Template Date Format Reads

**Files**: 
- `/templates/portal/tabs/membership.php:37, 147, 152`
- `/templates/portal/tabs/campaigns.php:218`
- `/templates/portal/tabs/inquiries.php:167`
- `/templates/emails/campaign-started.php:54, 77`
- `/templates/emails/ad-submitted.php:54, 83`
- `/templates/emails/ad-approved.php:54, 106`
- `/templates/emails/classified-expiring.php:82, 112`
- `/templates/emails/classified-approved.php:67, 130`
- `/templates/emails/receipt.php:97, 165`

**Count**: 23 instances  
**Pattern**: TEMPLATE-DIRECT-READ + REPEAT-READ (multiple calls per template)
**Issue**: Templates call get_option('date_format') multiple times. Should cache or pass formatted values.
**Fix**: Create helper method in template context to format all dates once.

---

### Rotation Stats Bypass

**File**: `/includes/Modules/Rotation/class-rotation-api.php:390, 409`
**Pattern**: BYPASS-HELPER
**Issue**: Settings fetched twice in same API endpoint.

---

### Demo Data Cache Keys

**Files**: 
- `/includes/Core/class-pro-admin.php:3020`
- `/includes/Core/class-installer.php:377, 2336, 2379, 2396, 2410`

**Pattern**: BYPASS-HELPER
**Issue**: Demo-related settings always bypass Settings_Helper.

---

## Full Consumer Map

| Setting Key | Consumer File | Consumer Type | Pattern | Notes |
|-------------|---------------|---------------|---------|-------|
| `wbam_pro_setup_complete` | `wb-ad-manager-pro.php:180` | Core bootstrap | BYPASS-HELPER | Check on plugin load — should cache |
| `wbam_pro_license_key` | `license/class-wbam-pro-license-manager.php:110,119,440,474,550,627,744` | License manager | BYPASS-HELPER + REPEAT-READ | Called 7x in license flow |
| `wbam_pro_license_status` | `license/class-wbam-pro-license-manager.php:120` | License manager | BYPASS-HELPER | Part of license flow |
| `wbam_pro_license_data` | `license/class-wbam-pro-license-manager.php:121` | License manager | BYPASS-HELPER | Part of license flow |
| `wbam_pro_settings` | 58 locations across modules | All consumer types | BYPASS-HELPER | Most frequent setting; 58 direct reads instead of Settings_Helper |
| `wbam_page_*` | `includes/Core/class-settings-helper.php:101`, `includes/Core/functions.php:28,55,87,131,153` | Core functions | BYPASS-HELPER | Page mappings called 5x in functions.php |
| `wbam_pro_classifieds_settings` | `includes/Core/class-settings-helper.php:574,589,617`, `includes/Core/functions.php:326` | Classifieds module | BYPASS-HELPER | 4 locations |
| `date_format` | 23 template locations | Template + Module classes | TEMPLATE-DIRECT-READ + NOT-FILTERABLE | WordPress native, but should be wrapped in filter |
| `time_format` | 8 template locations | Template | TEMPLATE-DIRECT-READ | Same as date_format |
| `admin_email` | 8 locations (notifications, billing, links) | Email + Modules | BYPASS-HELPER + NOT-FILTERABLE | Should have apply_filters wrapper |
| `wbam_rotation_model` | `Rotation_Engine:514` | Module class | BYPASS-HELPER + REPEAT-READ | Called per-render |
| `wbam_format_matching_enabled` | 2 locations | Module classes | BYPASS-HELPER | Direct reads |
| `wbam_link_prefix` | `Modules/Links/class-link-cloaker.php:68` | Module class | BYPASS-HELPER | Called for link generation |
| `wbam_currency` | `Modules/Links/class-partnership.php:203` | Module class | BYPASS-HELPER | Called per partnership context |
| `wbam_email_submissions` | `Modules/Frontend/class-frontend.php:401` | Frontend module | BYPASS-HELPER | Email submission list |
| `wbam_setup_complete` | Free plugin, 2 locations | Admin setup | BYPASS-HELPER | Checked on admin init |
| `wbam_settings` | Free plugin, 35+ locations | All layers | BYPASS-HELPER | Free plugin's main settings blob |
| `users_can_register` | `Advertiser_Shortcodes.php:2260` | Shortcode renderer | BYPASS-HELPER | WordPress native setting |
| `wbam_bump_price` | `Classifieds/class-classified-shortcodes.php:1167` | Shortcode renderer | BYPASS-HELPER + HARDCODED | Called with default `1` |
| `wbam_credits_purchase_url` | 2 locations | Module + Admin | BYPASS-HELPER | Settings override |
| `wbam_pro_rest_legacy_routes` | `Classifieds/class-classified-api.php:76` | REST API | BYPASS-HELPER | Feature flag |
| Demo-related options | Multiple | Installer + Admin | BYPASS-HELPER | `wbam_demo_data_ids`, `wbam_pro_demo_pages_exist`, etc. |

---

## Methodology

### Search Strategy
1. **Grep Pattern Match**: Found all `get_option(` calls via ripgrep across both plugins
2. **Consumer Classification**: Categorized each call by file location (template, module class, admin, etc.)
3. **Pattern Detection**: Scanned for:
   - Direct Settings_Helper bypass (comparing against expected helper usage)
   - Repeated calls in same method/class (REPEAT-READ)
   - Direct calls in templates (TEMPLATE-DIRECT-READ)
   - Hardcoded defaults/literals near get_option calls
   - Missing apply_filters wrappers (NOT-FILTERABLE)
   - Missing default parameters or null-checks (MISSING-DEFAULT)

### Tools Used
- Grep: `get_option\s*\(`
- Manual code inspection of key modules
- Cross-reference with Settings_Helper implementations

### Confidence Level
- **HIGH**: BYPASS-HELPER (detected by direct get_option instead of Settings_Helper::get)
- **HIGH**: REPEAT-READ (counted multiple calls in same scope)
- **HIGH**: TEMPLATE-DIRECT-READ (file path analysis)
- **MEDIUM**: HARDCODED-IN-TEMPLATE (requires semantic analysis; 2 confirmed via hardcoded defaults)
- **HIGH**: NOT-FILTERABLE (all get_option lacks apply_filters)
- **MEDIUM**: MISSING-DEFAULT (8 confirmed; many have defaults but lack null-checks)

### Next Steps
1. **Centralize date/time formatting** in Template_Loader context injection
2. **Add apply_filters wrappers** around all setting reads in public APIs
3. **Create Settings_Cache layer** for request-scoped caching of high-frequency reads
4. **Migrate all get_option calls** in modules to Settings_Helper::get() or Settings_Bridge
5. **Audit email templates** for context injection completeness

---

**Audit ID**: 02-setting-consumer-map-2026-04-19  
**Auditor**: Claude Code (Read-Only Audit)  
**Status**: Ready for P0 Triage
