# Plan: Canonicalize `pricing_model` enum

**Status:** ✅ Done (2026-04-18)
**Date:** 2026-04-18
**Risk class:** Data-flow critical (billing)
**Commit rule:** One callsite per commit. No sweeping rename commits.

## Execution summary (2026-04-18)

All 9 steps landed on branch `1.5.0` in 9 commits:

| Step | Commit  | Files | Outcome |
|------|---------|-------|---------|
| 1    | 6e7c76b | +2    | Pricing_Model enum class + plan doc |
| 2.1  | 96549e7 | 1     | Pricing_Calculator readers sanitize |
| 2.2  | 389e5ea | 1     | Package class readers sanitize |
| 2.3  | ab87b1c | 1     | Campaign_Manager reader sanitizes |
| 2.4  | 7167908 | 1     | Billing_Manager description branches sanitize |
| 3    | 5c61c54 | 4     | All 4 writer sites gated |
| 4    | 292649c | 3     | REST enums expose full canonical set |
| 5    | 7929446 | 2     | Admin UI exposes cpm_cpc + sanitizes display |
| 6    | 4511c49 | 1     | Demo-data stops writing 'ppc' |
| 7    | ecad76a | 1     | DB backfill migration (ppc -> cpc) runs |
| 8    | d3a3121 | 1     | Redundant Package::PRICING_MODELS + helper removed |

**DB state before → after:**
| Location | Before | After |
|----------|--------|-------|
| wp_wbam_packages rows with `ppc` | 1 | 0 |
| wp_wbam_campaigns rows with `ppc` | 2 | 0 |
| wbam_pro_db_version | 3.6.0 | 3.7.0 |

**Enum-consistency MCP check:** `pricing_model` no longer appears in the drift list (was 11 drifts, now 10).

**Browser verification passes:**
- /wp-admin/admin.php?page=wbam-packages — loads, 0 console errors
- /wp-admin/admin.php?page=wbam-campaigns — loads, 0 console errors
- /wp-admin/admin.php?page=wbam-revenue — loads, 0 console errors
- Package edit for id=9 (ex-ppc row): select shows `cpc` selected + 4 options present


---

## Problem

The `pricing_model` value is referenced in **17 files / 103 lines**. The allowed set disagrees across layers:

| Location | Allowed values | Notes |
|----------|---------------|-------|
| `Package::PRICING_MODELS` (package.php:167) | flat, cpm, cpc, **cpm_cpc** | Canonical constant (declared but not used everywhere) |
| REST enum — `pro-abilities.php:537` | flat, cpm, cpc | Missing `cpm_cpc` |
| REST enum — `package-api.php:194` | flat, cpm, cpc | Missing `cpm_cpc` |
| REST enum — `campaign-api.php:231` | flat, cpm, cpc | Missing `cpm_cpc` |
| `Pricing_Calculator::calculate_cost()` switch (pricing-calculator.php:67) | flat, cpm, cpc | No case for `cpm_cpc` — silent fallthrough |
| `Campaign_Manager::calculate_package_budget()` switch (campaign-manager.php:684) | flat, cpm, cpc, cpm_cpc | Handles `cpm_cpc` |
| `Advertiser_Shortcodes` (advertiser-shortcodes.php:1390, 1415) | cpm, cpc, cpm_cpc | "performance" predicate |
| `Billing_Manager` SQL WHERE (billing-manager.php:141) | cpm, cpc, cpm_cpc | |
| `demo-data-setup.php:1872` | cpm, cpc, **ppc** | Dead value — `ppc` is not in the canonical list |

## Live DB state (ran 2026-04-18 on wp-ads.local)

```sql
SELECT pricing_model, COUNT(*) FROM wp_wbam_campaigns GROUP BY pricing_model;
-- cpm: 2, flat: 5

SELECT pricing_model, COUNT(*) FROM wp_wbam_packages GROUP BY pricing_model;
-- cpc: 1, cpm: 1, flat: 7, ppc: 1   ← DEAD VALUE IN DB
```

**Evidence of silent bug:** One package row stores `ppc`. No reader understands `ppc`. When `Pricing_Calculator` hits it, the switch falls through to the default branch and returns `$flat_price`. Billing computed against that package is wrong by definition — but silent.

## Canonical decision

- **Canonical set:** `['flat', 'cpm', 'cpc', 'cpm_cpc']`
- `ppc` is **not** canonical. It must be migrated to `cpc` (legacy alias) or removed (dead demo data).
- Single source of truth: `Package::PRICING_MODELS` gets promoted to a shared enum class, `WBAM_Pro\Core\Enums\Pricing_Model`.

## Target shape

```php
// includes/Core/Enums/class-pricing-model.php (new)
final class Pricing_Model {
    public const FLAT     = 'flat';
    public const CPM      = 'cpm';
    public const CPC      = 'cpc';
    public const CPM_CPC  = 'cpm_cpc';

    public const ALL = array( self::FLAT, self::CPM, self::CPC, self::CPM_CPC );

    public static function is_valid( string $value ): bool {
        return in_array( $value, self::ALL, true );
    }

    public static function sanitize( $value, string $default = self::FLAT ): string {
        $value = is_string( $value ) ? $value : '';
        if ( self::is_valid( $value ) ) {
            return $value;
        }
        // Legacy alias: some demo rows stored 'ppc' (treat as cpc).
        if ( 'ppc' === $value ) {
            return self::CPC;
        }
        return $default;
    }
}
```

## Migration order (readers → sanitizers → writers → schema)

**Each step is one commit. After each: run affected feature in browser.**

### Step 1 — Add enum class (no callers yet)
- Create `includes/Core/Enums/class-pricing-model.php`.
- Add unit test: round-trip every canonical value + `ppc` → cpc + unknown → flat.
- **Regression surface:** zero. Nothing calls it.

### Step 2 — Migrate READERS to accept the superset (via `Pricing_Model::sanitize()`)
Readers are already lenient (switch default = flat). Migrating them first can NOT break anything — they accept at least the current set, and after migration they also map `ppc → cpc`. This is the "widen the throat" step.

Files & lines, in order:
1. `includes/Core/class-pricing-calculator.php` (4 switches: lines 67, 222, 244, 428)
2. `includes/Modules/Packages/class-package.php` (3 switches: lines 359-368, 559, get_formatted_price label)
3. `includes/Modules/Campaigns/class-campaign-manager.php` switch at 684
4. `includes/Modules/Wallet/class-billing-manager.php` at 190-213

For each: replace inline switch with `Pricing_Model::sanitize( $model )` then switch on canonical values. Keep existing branches identical. **One commit per file.**

**Regression check per file:**
- Open `/wp-admin/admin.php?page=wbam-packages` → verify price column still renders.
- Open campaign detail page → verify budget still renders.
- Open billing dashboard → verify no PHP warnings in debug.log.

### Step 3 — Migrate SANITIZERS (write gate)
Funnel all writes through `Pricing_Model::sanitize()` so new `ppc` can never enter the DB.

1. `Modules/Packages/class-package-manager.php` lines 85, 157
2. `Modules/Campaigns/class-campaign-manager.php` lines 92, 161
3. `Core/class-pro-abilities.php` line 1702 (REST write)
4. `Core/class-pro-admin.php` line 5742 (admin POST handler)

Each: `$value = Pricing_Model::sanitize( $input )`.

### Step 4 — Update REST enum schemas
Now that readers handle `cpm_cpc` and writes are sanitized, expose the full canonical set in REST enums:

1. `Modules/Packages/class-package-api.php:194` → `Pricing_Model::ALL`
2. `Modules/Campaigns/class-campaign-api.php:231` → `Pricing_Model::ALL`
3. `Core/class-pro-abilities.php:537` → `Pricing_Model::ALL`

**Regression check:** REST `POST /wp-json/wbam-pro/v1/packages` with `pricing_model: 'cpm_cpc'` now accepted, not rejected with schema validation error.

### Step 5 — Migrate WRITERS (admin UI)
1. `Core/class-pro-admin.php:4993` — `<select name="pricing_model">` only offers flat/cpc/cpm. Add `cpm_cpc` option.
2. `Admin/class-packages-list-table.php:220` labels — add `cpm_cpc` label.

### Step 6 — Fix demo-data-setup.php
1. Line 1872: replace `'ppc'` with `'cpc'` in the `in_array` check. Never write `ppc` again.
2. Anywhere else demo data writes `pricing_model`: funnel through `Pricing_Model::sanitize()`.

### Step 7 — DB backfill
One commit, runs once on upgrade:

```php
// includes/Core/class-installer.php migration block for next version
$wpdb->query( "UPDATE {$wpdb->prefix}wbam_packages SET pricing_model = 'cpc' WHERE pricing_model = 'ppc'" );
$wpdb->query( "UPDATE {$wpdb->prefix}wbam_campaigns SET pricing_model = 'cpc' WHERE pricing_model = 'ppc'" );
```

Pre-flight on the live DB to confirm safety:
```sql
SELECT id, name, pricing_model, price, price_per_unit FROM wp_wbam_packages WHERE pricing_model = 'ppc';
```

### Step 8 — Remove the `get_valid_pricing_model()` private method in `class-package.php`
Replaced by `Pricing_Model::sanitize()`. Delete only after Step 2 lands.

### Step 9 — Re-run enum-consistency check
```
mcp__wp-plugin-qa__wppqa_check_enum_consistency plugin_path: .../wb-ad-manager-pro
```
Expect: `pricing_model` drift resolved (single consistent set everywhere).

---

## What we are NOT doing in this plan
- Not renaming any DB column.
- Not changing `pricing_model` semantics. `flat` stays `flat`, `cpm` stays `cpm`, etc.
- Not touching the other 10 enum drifts (`status`, `action`, `ad_type`, etc.). Each gets its own plan.
- Not touching the 30 half-wired settings. Separate plan.

## Rollback

Each step is its own commit. If any step regresses:
```bash
git revert <sha>
```
Readers-first order means a revert at Step N leaves the system in a consistent intermediate state (readers accept the superset, writers still emit the narrower set — the worst case is rejected writes for `cpm_cpc` until Step 4 lands, which is the current behavior anyway).

## Verification gates

Before merging this whole plan:
1. **Unit test** for `Pricing_Model::sanitize()` covering canonical values, `ppc`, unknown strings, non-strings.
2. **Browser test** per step (see above).
3. **PHPStan** level stays ≥ current baseline (646 errors on rotator; check before/after on ad-manager-pro).
4. **Enum-consistency MCP check** shows `pricing_model` removed from drift list.
5. `SELECT DISTINCT pricing_model FROM wp_wbam_packages, wp_wbam_campaigns` returns only canonical values.

## Estimated size
~9 commits, ~14 files touched. No bulk-rename commits.
