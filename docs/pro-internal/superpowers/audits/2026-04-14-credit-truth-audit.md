# Credit Ledger Truth Audit Report

**Plugin:** WB Ad Manager Pro v1.5.0  
**Branch:** 1.5.0  
**Commit:** f980cb6 (simplification)  
**Audit Date:** 2026-04-14  
**Auditor:** Claude Code  

---

## Executive Summary

The plugin has **INCOMPLETE migration** to the simplified credit model. While new modules correctly use `Credits_Bridge::charge/credit/topup/adjust`, critical legacy code paths still read/write the `$advertiser->balance` field directly, creating **silent corruption** where:

- Admin adjustments via user profile form go to `advertiser.balance` (not SDK ledger)
- REST API endpoints return stale `advertiser.balance` (not SDK-live value)
- Frontend abilities expose legacy balance (not SDK-live value)
- Classified refunds and other credits correctly write to SDK ledger but UI doesn't read from it
- Revenue dashboard still queries the removed `wbam_transactions` table

**Recommendation:** Migrate all balance reads to `Credits_Bridge::get_balance()` and all balance writes through `Credits_Bridge::*` primitives. Block direct edits of `Advertiser::balance` field.

---

## Critical (will break runtime / silent corruption)

### 1. REST API `/advertiser/wallet` returns stale balance from database

**File:** `includes/Modules/Advertisers/class-advertiser-api.php:413-435`

```php
public function get_wallet( $request ) {
    // ...
    return rest_ensure_response(
        array(
            'balance'           => $advertiser->balance,  // ← STALE
            'formatted_balance' => $advertiser->get_formatted_balance(),
        )
    );
}
```

**Issue:** Returns `$advertiser->balance` from database, which is not synced with SDK ledger. If user topped up credits via SDK, this endpoint shows old balance.

**Why it's wrong:** Per the spec, SDK ledger (`{prefix}_credit_ledger`) is the single source of truth. REST API must return live values from `Credits_Bridge::get_balance()`.

**Recommended fix:**
```php
$balance = \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser->id );
return rest_ensure_response(
    array(
        'balance'           => (int) $balance,
        'formatted_balance' => $balance . ' ' . _n( 'credit', 'credits', $balance, 'wb-ad-manager-pro' ),
    )
);
```

---

### 2. REST API `/advertiser/transactions` reads from legacy `wbam_transactions` table (removed)

**File:** `includes/Modules/Advertisers/class-advertiser-api.php:443-498`

```php
public function get_transactions( $request ) {
    // ...
    $table = $wpdb->prefix . 'wbam_transactions';  // ← REMOVED TABLE
    
    // Queries removed table
    $total = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where}",
            $values
        )
    );
    
    $transactions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $values
        )
    );
}
```

**Issue:** The `wbam_transactions` table was removed in v1.5.0 (per comment in Installer line 1342). Querying it will return empty results or trigger errors if the table doesn't exist on fresh installs.

**Why it's wrong:** Should use SDK ledger via `Credits_Bridge::get_ledger()` or `Credits_Bridge::query_ledger()` as the single source of truth.

**Recommended fix:**
```php
$advertiser = $manager->get_by_user( $user_id );
if ( ! $advertiser ) {
    return rest_ensure_response( array( 'transactions' => array(), 'total' => 0 ) );
}

$offset = ( $page - 1 ) * $per_page;
$entries = \WBAM_Pro\Core\Credits_Bridge::get_ledger( 
    $advertiser->id, 
    $per_page, 
    $offset 
);

$transactions = array();
foreach ( $entries as $entry ) {
    $transactions[] = array(
        'id'         => $entry->id,
        'type'       => $entry->entry_type,
        'amount'     => (float) $entry->amount,
        'item_id'    => (int) $entry->item_id,
        'note'       => $entry->note,
        'created_at' => $entry->created_at,
    );
}
```

---

### 3. Abilities REST endpoint `wbam-pro/get-balance` returns stale `$advertiser->balance`

**File:** `includes/Core/class-pro-abilities.php:1770-1793`

```php
public function execute_get_balance( $input ) {
    // ...
    return array(
        'balance'  => (float) $advertiser->balance,  // ← STALE
        'currency' => Settings_Helper::get( 'currency', 'USD' ),
    );
}
```

**Issue:** Third-party code consuming this ability gets outdated balance, not SDK-live value.

**Why it's wrong:** Per spec, SDK ledger is single source of truth. This should call `Credits_Bridge::get_balance()`.

**Recommended fix:**
```php
return array(
    'balance'  => (float) \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser->id ),
    'currency' => Settings_Helper::get( 'currency', 'USD' ),
);
```

---

### 4. Abilities REST endpoint `wbam-pro/list-advertisers` returns stale `$advertiser->balance`

**File:** `includes/Core/class-pro-abilities.php:1849-1876`

```php
public function execute_list_advertisers( $input ) {
    // ...
    foreach ( $advertisers as $advertiser ) {
        $items[] = array(
            // ...
            'balance'      => (float) $advertiser->balance,  // ← STALE for each
            'total_spent'  => (float) $advertiser->total_spent,
            // ...
        );
    }
}
```

**Issue:** Bulk advertiser list shows outdated balances, not SDK-live values.

**Why it's wrong:** All balance reads must use SDK ledger.

**Recommended fix:**
```php
foreach ( $advertisers as $advertiser ) {
    $items[] = array(
        'id'           => $advertiser->id,
        'user_id'      => $advertiser->user_id,
        'company_name' => $advertiser->company_name,
        'balance'      => (int) \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser->id ),
        'total_spent'  => (float) $advertiser->total_spent,
        'status'       => $advertiser->status,
        'created_at'   => $advertiser->created_at,
    );
}
```

---

### 5. Abilities REST endpoint `wbam-pro/get-advertiser` returns stale balance

**File:** `includes/Core/class-pro-abilities.php:1884-1930`

```php
return array(
    // ...
    'balance'         => (float) $advertiser->balance,  // ← STALE
    'total_spent'     => (float) $advertiser->total_spent,
    // ...
);
```

**Issue:** Admin inspecting an advertiser's balance via abilities gets stale value.

**Why it's wrong:** Single source of truth is SDK ledger.

**Recommended fix:**
```php
return array(
    // ...
    'balance'         => (int) \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser->id ),
    'total_spent'     => (float) $advertiser->total_spent,
    // ...
);
```

---

### 6. Admin user profile balance field directly edits `advertiser.balance` (bypasses SDK)

**File:** `includes/Modules/Advertisers/class-user-profile-integration.php:252-262`

```php
if ( current_user_can( 'manage_options' ) ) {
    if ( isset( $_POST['wbam_balance'] ) ) {
        $advertiser->balance = floatval( $_POST['wbam_balance'] );  // ← DIRECT EDIT
    }
}

$advertiser->save();  // ← Writes to advertiser.balance column, not SDK ledger
```

**Issue:** Admin manually editing balance on user profile creates a ledger entry in nowhere. The `advertiser.balance` column is updated, but SDK ledger never receives the corresponding deduction/topup, so balance diverges.

**Why it's wrong:** All balance mutations must write to SDK ledger via `Credits_Bridge::adjust()`.

**Recommended fix:**
```php
if ( current_user_can( 'manage_options' ) ) {
    if ( isset( $_POST['wbam_balance'] ) ) {
        $new_balance = floatval( $_POST['wbam_balance'] );
        $old_balance = \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser->id );
        $delta = $new_balance - $old_balance;
        
        if ( $delta !== 0 ) {
            \WBAM_Pro\Core\Credits_Bridge::adjust(
                $advertiser->id,
                (int) $delta,
                'Admin user profile adjustment'
            );
        }
        // Do NOT update $advertiser->balance — it's now managed by SDK
    }
}
```

---

### 7. Admin Advertiser_Manager::adjust_balance() uses legacy `credit_balance/debit_balance` methods

**File:** `includes/Modules/Advertisers/class-advertiser-manager.php:295-325`

```php
public function adjust_balance( $advertiser_id, $amount, $reason = '' ) {
    $advertiser = $this->get( $advertiser_id );
    // ...
    
    if ( $amount > 0 ) {
        $result = $advertiser->credit_balance( $amount, $reason );  // ← LEGACY
    } else {
        $result = $advertiser->debit_balance( abs( $amount ), $reason );  // ← LEGACY
    }
}
```

**Issue:** `Advertiser::credit_balance()` and `Advertiser::debit_balance()` directly modify `$this->balance` and save to the database column. They do NOT write to SDK ledger, so balance diverges.

**Why it's wrong:** Should use `Credits_Bridge::adjust()` which writes to SDK ledger.

**Recommended fix:**
```php
public function adjust_balance( $advertiser_id, $amount, $reason = '' ) {
    $advertiser = $this->get( $advertiser_id );
    if ( ! $advertiser ) {
        return false;
    }
    
    $old_balance = \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser_id );
    $amount = (int) $amount;  // Cast to int for credits
    
    if ( 0 === $amount ) {
        return false;
    }
    
    $result = \WBAM_Pro\Core\Credits_Bridge::adjust(
        $advertiser_id,
        $amount,
        $reason ?: 'Balance adjustment'
    );
    
    if ( $result ) {
        Audit_Logger::get_instance()->log(
            'balance_adjusted',
            'advertiser',
            $advertiser_id,
            array( 'balance' => $old_balance ),
            array( 
                'balance' => \WBAM_Pro\Core\Credits_Bridge::get_balance( $advertiser_id ),
                'reason' => $reason,
            )
        );
    }
    
    return (bool) $result;
}
```

---

### 8. Advertiser model has legacy `credit_balance()` and `debit_balance()` methods still in use

**File:** `includes/Modules/Advertisers/class-advertiser.php:446-499`

```php
public function credit_balance( $amount, $note = '' ) {
    // ...
    $this->balance += $amount;  // ← DIRECT MUTATION
    // ...
    return $this->save();  // ← Writes to DB column only, not SDK ledger
}

public function debit_balance( $amount, $note = '' ) {
    // ...
    $this->balance -= $amount;  // ← DIRECT MUTATION
    // ...
    return $this->save();  // ← Writes to DB column only, not SDK ledger
}
```

**Issue:** These methods are called by `Advertiser_Manager::adjust_balance()` (see issue #7) and possibly other legacy code. They bypass SDK ledger entirely.

**Why it's wrong:** All balance writes must go through SDK ledger.

**Recommended fix:** Remove these methods entirely (they're dead code). Replace all callers with `Credits_Bridge::charge()`, `Credits_Bridge::credit()`, or `Credits_Bridge::adjust()`.

---

### 9. Revenue dashboard queries removed `wbam_transactions` table (missing)

**File:** `includes/Admin/class-revenue-dashboard.php:699-742`

```php
FROM {$wpdb->prefix}wbam_transactions
WHERE type IN ('debit', 'classified')
AND status = 'completed'
```

**Issue:** The `wbam_transactions` table was removed in v1.5.0. Dashboard queries will return empty or error on fresh installs.

**Why it's wrong:** Should query SDK ledger via `Credits_Bridge::query_ledger()`.

**Recommended fix:**
```php
// Use Credits_Bridge::query_ledger() instead
$entries = \WBAM_Pro\Core\Credits_Bridge::query_ledger( array(
    'entry_type' => array( 'deduction' ),  // SDK entry types
    'date_from'  => $start_date,
    'date_to'    => $end_date,
) );

$revenue_stats = array(
    'featured_revenue'  => 0,
    'renewal_revenue'   => 0,
    'upgrade_revenue'   => 0,
    'total_revenue'     => 0,
    'transaction_count' => 0,
);

foreach ( $entries as $entry ) {
    if ( strpos( $entry->note, 'Featured' ) !== false || 
         strpos( $entry->note, 'featured' ) !== false ) {
        $revenue_stats['featured_revenue'] += abs( (int) $entry->amount );
    }
    // ... classify other entry types
    $revenue_stats['total_revenue'] += abs( (int) $entry->amount );
    ++$revenue_stats['transaction_count'];
}
```

---

## High (visible mismatch, wrong number shown to user/admin)

### 10. Display formatting still uses `wbam_format_price()` which adds currency symbols

**File:** `includes/Core/functions.php:211-219`

```php
function wbam_format_price( $price ) {
    $symbol   = wbam_get_currency_symbol();  // e.g., "$"
    $currency = wbam_get_currency_code();
    
    $decimals = ( 'JPY' === $currency ) ? 0 : 2;
    
    return $symbol . number_format( (float) $price, $decimals );
    // Returns: "$100.00" instead of "100 credits"
}
```

**Issue:** Credits are displayed as money ("$100.00") instead of credits ("100 credits"). Per the simplified model, credits should NEVER show currency symbols.

**Why it's wrong:** Per spec, every UI surface should show "N credits" (no $ sign).

**Recommended fix:**
```php
function wbam_format_credits( $amount ) {
    $amount = absint( $amount );
    return sprintf( 
        '%s %s',
        number_format_i18n( $amount ),
        _n( 'credit', 'credits', $amount, 'wb-ad-manager-pro' )
    );
}
```

Then replace all `wbam_format_price( $balance )` with `wbam_format_credits( $balance )` in balance displays.

---

### 11. Advertiser model's `get_formatted_balance()` uses legacy `wbam_format_price()`

**File:** `includes/Modules/Advertisers/class-advertiser.php:426-428`

```php
public function get_formatted_balance() {
    return wbam_format_price( $this->balance );  // ← Returns "$100.00"
}
```

**Issue:** Returns money format "$100.00" instead of "100 credits".

**Why it's wrong:** Per spec, should display credits without currency symbol.

**Recommended fix:**
```php
public function get_formatted_balance() {
    $balance = \WBAM_Pro\Core\Credits_Bridge::get_balance( $this->id );
    return wbam_format_credits( $balance );
}
```

---

## Medium (legacy code still around, no current bug)

### 12. `Advertiser::get_balance()` reads stale `$this->balance` instead of SDK ledger

**File:** `includes/Modules/Advertisers/class-advertiser.php:417-419`

```php
public function get_balance() {
    return $this->balance;  // ← STALE, should read SDK ledger
}
```

**Issue:** Returns database column value, not live SDK balance. All internal code should use `Credits_Bridge::get_balance()` instead.

**Why it's wrong:** This is a trap method that will cause bugs if code calls `$advertiser->get_balance()` expecting live values.

**Recommended fix:**
```php
public function get_balance() {
    // Deprecated — read from SDK ledger instead
    return \WBAM_Pro\Core\Credits_Bridge::get_balance( $this->id );
}
```

Or remove entirely and update all callers to use `Credits_Bridge::get_balance()`.

---

### 13. `Advertiser::to_array()` includes stale balance field

**File:** `includes/Modules/Advertisers/class-advertiser.php:873-888`

```php
public function to_array() {
    return array(
        // ...
        'balance'               => $this->balance,  // ← STALE
        'total_spent'           => $this->total_spent,
        // ...
    );
}
```

**Issue:** When advertiser data is serialized (for audit logs, API responses), it includes stale balance. Audit logs will record wrong balance values.

**Why it's wrong:** Should include SDK-live balance.

**Recommended fix:**
```php
public function to_array() {
    return array(
        'id'                    => $this->id,
        'user_id'               => $this->user_id,
        'company_name'          => $this->company_name,
        'status'                => $this->status,
        'balance'               => (int) \WBAM_Pro\Core\Credits_Bridge::get_balance( $this->id ),
        'total_spent'           => (float) $this->total_spent,
        'website'               => $this->website,
        'phone'                 => $this->phone,
        'address'               => $this->address,
        'notification_settings' => $this->notification_settings,
        'created_at'            => $this->created_at,
        'updated_at'            => $this->updated_at,
    );
}
```

---

## Low (cosmetic / docblock / dead constants)

None found. All found issues are Critical or High severity.

---

## Summary by Category

### Reads (must use `Credits_Bridge::get_balance()`)

- ❌ `Advertiser::get_balance()` — line 418
- ❌ `Advertiser_API::get_wallet()` — line 429
- ❌ `Advertiser_API::get_transactions()` — line 458 (queries removed table)
- ❌ `Pro_Abilities::execute_get_balance()` — line 1790
- ❌ `Pro_Abilities::execute_list_advertisers()` — line 1868
- ❌ `Pro_Abilities::execute_get_advertiser()` — line 1923
- ❌ `User_Profile_Integration::render_advertiser_fields()` — line 90 (display only, not critical)
- ✅ `Advertiser_Shortcodes` — lines 424, 788, 1921 (correctly uses SDK)

### Writes (must use `Credits_Bridge` primitives)

- ❌ `Advertiser::credit_balance()` — line 453 (direct mutation)
- ❌ `Advertiser::debit_balance()` — line 481 (direct mutation)
- ❌ `Advertiser_Manager::adjust_balance()` — line 305 (calls legacy methods)
- ❌ `User_Profile_Integration::save_advertiser_fields()` — line 258 (direct field edit)
- ✅ `Campaign_Manager::update_status()` — uses `Credits_Bridge::charge/credit`
- ✅ `Classified_Manager::process_refund()` — uses `Credits_Bridge::credit`
- ✅ `Ad_Submission_Manager::process_ad_submission()` — uses `Credits_Bridge::charge`
- ✅ `Pro_Admin::adjust_balance_handler()` — line 5053 (correctly uses SDK)

### Display/Format (should show "N credits", no currency)

- ❌ `wbam_format_price()` — shows "$100.00" instead of "100 credits"
- ❌ `Advertiser::get_formatted_balance()` — uses legacy formatter
- ✅ `Advertiser_Shortcodes` — lines 432, 432 (shows "X credit(s)" correctly)

### Transactions Surface (should read from SDK ledger only)

- ❌ `Advertiser_API::get_transactions()` — queries removed `wbam_transactions` table
- ❌ `Revenue_Dashboard` — queries removed `wbam_transactions` table (2 locations)
- ✅ `Transactions_List_Table` — uses `Credits_Bridge::get_ledger()` correctly

### Method Residue (removed methods should not be called)

- ✅ Zero calls to `Credits_Bridge::hold/deduct/refund/cancel_hold` (methods don't exist, would fatal)
- ❌ But legacy `Advertiser::credit_balance/debit_balance` still exist and are called by `adjust_balance()`

### REST API Surface (should return SDK values)

- ❌ GET `/advertiser/wallet` — returns stale balance
- ❌ GET `/advertiser/transactions` — reads removed table
- ❌ Abilities `get-balance` — returns stale balance
- ❌ Abilities `list-advertisers` — returns stale balances
- ❌ Abilities `get-advertiser` — returns stale balance

---

## Recommended Fix Priority

1. **CRITICAL (week 1):** Fix REST API endpoints and abilities to return SDK-live balance
2. **CRITICAL (week 1):** Fix admin balance adjustment to write to SDK ledger
3. **CRITICAL (week 1):** Fix legacy transactions queries to use SDK ledger
4. **HIGH (week 2):** Change balance display format from "$100.00" to "100 credits"
5. **MEDIUM (week 2):** Deprecate/remove legacy `credit_balance/debit_balance` methods
6. **MEDIUM (week 3):** Remove `wbam_transactions` table from Installer schema comments

---

## Testing Checklist

After fixes:

- [ ] Admin edits balance on user profile → SDK ledger reflects change
- [ ] Admin uses "Adjust Balance" button → SDK ledger reflects change
- [ ] REST API `/advertiser/wallet` returns same value as `Credits_Bridge::get_balance()`
- [ ] REST API `/advertiser/transactions` shows SDK ledger entries
- [ ] Abilities return SDK-live balance values
- [ ] Revenue dashboard queries SDK ledger, not dead `wbam_transactions` table
- [ ] All balance displays show "N credits", not "$N.NN"
- [ ] Audit logs show SDK balance, not DB column value
- [ ] No direct reads of `advertiser.balance` outside Advertiser model
- [ ] No direct writes to `advertiser.balance` anywhere

