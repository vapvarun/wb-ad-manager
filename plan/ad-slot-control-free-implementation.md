# Ad Slot Control (Free) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every WB Ad Manager site a two-level control over which placement slots exist and which are sellable to advertisers, backed by one non-drifting placement registry.

**Architecture:** One new method on `Placement_Engine` becomes the single source of truth for "which slots may an ad be assigned to". Two new array settings gate it — a site gate that also stops delivery, and an advertiser gate that is selection-only. The two consumers that currently read different lists both switch to the new method, which fixes an existing drift bug as a side effect.

**Tech Stack:** PHP 7.4+, WordPress 6.7+, WP Settings API, PHPUnit 9.5 (`WP_UnitTestCase`), PHPCS (WPCS), PHPStan level 5.

**Repo:** `wb-ads-rotator-with-split-test` — branch `feature/ad-slot-control`, PR to `main`.

**Design spec:** [`plan/ad-slot-control.md`](ad-slot-control.md) — read §2, §3 before starting.

---

## Global Constraints

- Text domain is `wb-ads-rotator-with-split-test` on every translatable string.
- Namespace `WBAM\`, prefix `wbam_`, constants `WBAM_*`.
- `Admin\Settings::sanitize_settings()` **rebuilds the option from scratch** — any key it does not explicitly write is dropped on every save. Both new keys MUST be written there or they silently reset. This is the single highest-risk detail in this plan.
- Both new settings default to `array()`, and an empty array means **all placements**. This is what keeps existing installs behaving identically on upgrade. Never change this to mean "none".
- The advertiser list is always intersected with the site list server-side. Never trust the posted value.
- No new DB tables and no new options — both keys live in the existing `wbam_settings` option.
- PHPCS and PHPStan must pass. Run `composer lint-fix` before `composer lint`.
- The push gate is `composer verify-no-test`. Do not push on a red gate.
- Tests live in `tests/free/`, namespace `WBAM\Tests\Free`, extending `WP_UnitTestCase`.
- **Test command — `composer test-free` does not work.** The bootstrap looks in `$TMPDIR` but the scaffold lives at `/tmp/wordpress-tests-lib`. Always run:

  ```bash
  WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite=free
  ```

- **Baseline is not green.** On the untouched branch: `Tests: 19, Failures: 3, Skipped: 1`. Pre-existing, not yours, do not fix:
  - `Test_CPT::test_wbam_ad_registered_with_rest_support`
  - `Test_CPT::test_ad_supports_title_and_content`
  - `Test_Installer::test_default_settings_option_seeded`
  - skipped: `Test_Click_Tracking::test_click_produces_analytics_row`

  Success bar for every task: your new tests pass, and the above stays at exactly 3 failures / 1 skip. If a fourth failure appears, you caused it.
- The plugin's `CLAUDE.md` "Quick Reference" is stale — it claims CSS `.wbarst-`, JS `wbARST`, REST `wb-ads/v1`, options `wb_ads_rotator_`. The real code uses `wbam_` (1099 occurrences; `wbarst_` appears zero times). **The code governs.**
- Per the plugin's `CLAUDE.md`, invoke the `/wp-plugin-development` skill before any code change.
- Commit after every task. No co-author attribution, no "Generated with" footer.

---

## File Structure

| File | Responsibility |
|---|---|
| `includes/Modules/Placements/class-placement-engine.php` | **Modify.** Add `get_selectable_placements()` (single source of truth) and gate `get_ads_for_placement()`. |
| `includes/Core/class-settings-helper.php` | **Modify.** Add `enabled_placements()` / `advertiser_placements()` static readers so both plugins resolve the gates identically without instantiating the admin Settings class. |
| `includes/Admin/class-settings.php` | **Modify.** Defaults, sanitizer, new Placements section + field renderer. |
| `includes/Admin/class-placement-settings.php` | **Create.** The Placements settings table: row rendering and the active-ad count query. Kept out of `class-settings.php`, which is already ~800 lines. |
| `includes/Admin/class-admin.php:920-945` | **Modify.** Metabox switches to `get_selectable_placements()`. |
| `includes/Core/class-placement-format-map.php:62-96` | **Modify.** `seed_from_engine()` switches to `get_selectable_placements()`. |
| `assets/js/admin-placement-settings.js` | **Create.** Site-unticks-advertiser coupling + confirm-on-disable. |
| `tests/free/test-placement-gates.php` | **Create.** Gate resolution, intersection, empty-means-all. |
| `tests/free/test-placement-registry-parity.php` | **Create.** Regression test for the metabox/registry drift bug. |

**Why a separate `class-placement-settings.php`:** the count query, the row markup and the grouping logic are one cohesive responsibility with its own test surface. `class-settings.php` already carries 8 sections and ~800 lines; adding a table renderer to it would make the file harder to hold in context, and the count query needs its own unit test that shouldn't require booting the whole settings screen.

---

## Task 1: Settings helper — resolve both gates

**Files:**
- Modify: `includes/Core/class-settings-helper.php`
- Test: `tests/free/test-placement-gates.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces:
  - `Settings_Helper::enabled_placements(): string[]` — site gate. `array()` means all.
  - `Settings_Helper::advertiser_placements(): string[]` — advertiser gate, already intersected with the site gate. `array()` means all.
  - Filters `wbam_enabled_placements` and `wbam_advertiser_placements`, both receiving `string[]` and expected to return `string[]`.

Every later task and the Pro plugin read the gates through these two methods. Nothing else may read the raw option keys.

- [ ] **Step 1: Write the failing test**

Create `tests/free/test-placement-gates.php`:

```php
<?php
/**
 * Placement gate resolution: site + advertiser allowlists.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Settings_Helper;
use WP_UnitTestCase;

class Test_Placement_Gates extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	public function test_empty_site_gate_means_all(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array() ) );
		$this->assertSame( array(), Settings_Helper::enabled_placements() );
	}

	public function test_missing_key_means_all(): void {
		update_option( 'wbam_settings', array() );
		$this->assertSame( array(), Settings_Helper::enabled_placements() );
	}

	public function test_site_gate_returns_sanitized_ids(): void {
		update_option(
			'wbam_settings',
			array( 'enabled_placements' => array( 'header', 'Foo Bar', 'footer' ) )
		);
		$this->assertSame(
			array( 'header', 'foobar', 'footer' ),
			Settings_Helper::enabled_placements()
		);
	}

	public function test_advertiser_gate_is_intersected_with_site_gate(): void {
		update_option(
			'wbam_settings',
			array(
				'enabled_placements'    => array( 'header', 'footer' ),
				'advertiser_placements' => array( 'header', 'popup' ),
			)
		);
		// 'popup' is not in the site gate, so it cannot be sellable.
		$this->assertSame( array( 'header' ), Settings_Helper::advertiser_placements() );
	}

	public function test_empty_advertiser_gate_falls_back_to_site_gate(): void {
		update_option(
			'wbam_settings',
			array(
				'enabled_placements'    => array( 'header', 'footer' ),
				'advertiser_placements' => array(),
			)
		);
		$this->assertSame( array( 'header', 'footer' ), Settings_Helper::advertiser_placements() );
	}

	public function test_site_gate_filter_overrides_option(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );
		add_filter( 'wbam_enabled_placements', static fn() => array( 'footer' ) );
		$this->assertSame( array( 'footer' ), Settings_Helper::enabled_placements() );
		remove_all_filters( 'wbam_enabled_placements' );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
composer test -- --testsuite free --filter Test_Placement_Gates
```

Expected: FAIL — `Error: Call to undefined method WBAM\Core\Settings_Helper::enabled_placements()`.

- [ ] **Step 3: Write minimal implementation**

Add to `includes/Core/class-settings-helper.php`:

```php
	/**
	 * Placement IDs usable on this site.
	 *
	 * An empty array means "all placements" — that is what keeps existing
	 * installs behaving identically after upgrade. It must never be
	 * reinterpreted as "none".
	 *
	 * @since 2.11.0
	 * @return string[] Placement IDs, or empty for all.
	 */
	public static function enabled_placements() {
		$settings = get_option( 'wbam_settings', array() );
		$ids      = isset( $settings['enabled_placements'] ) && is_array( $settings['enabled_placements'] )
			? $settings['enabled_placements']
			: array();

		$ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', $ids ) ) ) );

		/**
		 * Filter the placements usable on this site.
		 *
		 * @since 2.11.0
		 * @param string[] $ids Placement IDs. Empty array means all.
		 */
		return (array) apply_filters( 'wbam_enabled_placements', $ids );
	}

	/**
	 * Placement IDs that may be sold to advertisers.
	 *
	 * Always a subset of enabled_placements(): a slot the site has closed
	 * can never be sellable, whatever the stored value says. An empty
	 * stored value falls back to the site gate rather than to "none", so
	 * an admin who never opens this screen keeps today's behaviour.
	 *
	 * @since 2.11.0
	 * @return string[] Placement IDs, or empty for all.
	 */
	public static function advertiser_placements() {
		$settings = get_option( 'wbam_settings', array() );
		$ids      = isset( $settings['advertiser_placements'] ) && is_array( $settings['advertiser_placements'] )
			? $settings['advertiser_placements']
			: array();

		$ids  = array_values( array_unique( array_filter( array_map( 'sanitize_key', $ids ) ) ) );
		$site = self::enabled_placements();

		if ( empty( $ids ) ) {
			$ids = $site;
		} elseif ( ! empty( $site ) ) {
			$ids = array_values( array_intersect( $ids, $site ) );
		}

		/**
		 * Filter the placements sellable to advertisers.
		 *
		 * @since 2.11.0
		 * @param string[] $ids Placement IDs. Empty array means all.
		 */
		return (array) apply_filters( 'wbam_advertiser_placements', $ids );
	}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
composer test -- --testsuite free --filter Test_Placement_Gates
```

Expected: PASS, 6 assertions green.

- [ ] **Step 5: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Core/class-settings-helper.php tests/free/test-placement-gates.php
git commit -m "feat: resolve site and advertiser placement gates in Settings_Helper"
```

---

## Task 2: Single source of truth on Placement_Engine

**Files:**
- Modify: `includes/Modules/Placements/class-placement-engine.php`
- Test: `tests/free/test-placement-gates.php` (append)

**Interfaces:**
- Consumes: `Settings_Helper::enabled_placements()` from Task 1.
- Produces: `Placement_Engine::get_selectable_placements(): Placement_Interface[]` keyed by placement ID. Tasks 4, 5 and 6 all call this and nothing else.

- [ ] **Step 1: Write the failing test**

Append to `tests/free/test-placement-gates.php`, inside the class:

```php
	public function test_selectable_placements_respects_site_gate(): void {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );
		$ids = array_keys( $engine->get_selectable_placements() );

		$this->assertContains( 'header', $ids );
		$this->assertNotContains( 'footer', $ids );
	}

	public function test_selectable_placements_empty_gate_returns_all_visible(): void {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		update_option( 'wbam_settings', array( 'enabled_placements' => array() ) );
		$ids = array_keys( $engine->get_selectable_placements() );

		$this->assertContains( 'header', $ids );
		$this->assertContains( 'footer', $ids );
	}

	public function test_selectable_placements_excludes_non_selector_placements(): void {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		update_option( 'wbam_settings', array() );
		$ids = array_keys( $engine->get_selectable_placements() );

		// Shortcode_Placement::show_in_selector() returns false — it is
		// used manually via [wbam_ad], never assigned to an ad.
		$this->assertNotContains( 'shortcode', $ids );
	}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
composer test -- --testsuite free --filter Test_Placement_Gates
```

Expected: FAIL — `Call to undefined method ...Placement_Engine::get_selectable_placements()`.

- [ ] **Step 3: Write minimal implementation**

Add to `class-placement-engine.php`, after `get_placements_grouped()` (line ~194):

```php
	/**
	 * Placements an ad may be assigned to on this site.
	 *
	 * Applies, in order: is_available(), show_in_selector(), and the site
	 * allowlist. This is the ONLY method admin UI and the portal registry
	 * may use to build a placement list. Reading $this->placements
	 * directly reintroduces the drift this method exists to remove — the
	 * ad edit metabox and the advertiser portal previously read two
	 * different lists, so filtering one silently missed the other.
	 *
	 * @since 2.11.0
	 * @return Placement_Interface[] Keyed by placement ID.
	 */
	public function get_selectable_placements() {
		$allowed = \WBAM\Core\Settings_Helper::enabled_placements();
		$out     = array();

		foreach ( $this->placements as $id => $placement ) {
			if ( ! $placement->is_available() || ! $placement->show_in_selector() ) {
				continue;
			}

			// Empty allowlist means all placements (see Settings_Helper).
			if ( ! empty( $allowed ) && ! in_array( $id, $allowed, true ) ) {
				continue;
			}

			$out[ $id ] = $placement;
		}

		return $out;
	}

	/**
	 * get_selectable_placements() grouped by Placement_Interface::get_group().
	 *
	 * @since 2.11.0
	 * @return array<string, Placement_Interface[]>
	 */
	public function get_selectable_placements_grouped() {
		$grouped = array();

		foreach ( $this->get_selectable_placements() as $id => $placement ) {
			$grouped[ $placement->get_group() ][ $id ] = $placement;
		}

		return $grouped;
	}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
composer test -- --testsuite free --filter Test_Placement_Gates
```

Expected: PASS, 9 assertions green.

- [ ] **Step 5: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Modules/Placements/class-placement-engine.php tests/free/test-placement-gates.php
git commit -m "feat: add get_selectable_placements as the single placement source of truth"
```

---

## Task 3: Settings defaults and sanitizer

**Files:**
- Modify: `includes/Admin/class-settings.php` (defaults array ~line 39, `sanitize_settings()` ~line 478)
- Test: `tests/free/test-placement-gates.php` (append)

**Interfaces:**
- Consumes: nothing.
- Produces: `wbam_settings['enabled_placements']` and `wbam_settings['advertiser_placements']`, both `string[]`, both surviving a settings save.

**Critical:** `sanitize_settings()` rebuilds the option from scratch. If these two keys are not written there, every save on the Settings screen wipes them.

- [ ] **Step 1: Write the failing test**

Append to `tests/free/test-placement-gates.php`, inside the class:

```php
	public function test_sanitizer_preserves_placement_gates(): void {
		$settings = new \WBAM\Admin\Settings();

		$out = $settings->sanitize_settings(
			array(
				'enabled_placements'    => array( 'header', 'footer' ),
				'advertiser_placements' => array( 'header' ),
			)
		);

		$this->assertSame( array( 'header', 'footer' ), $out['enabled_placements'] );
		$this->assertSame( array( 'header' ), $out['advertiser_placements'] );
	}

	public function test_sanitizer_intersects_advertiser_with_site(): void {
		$settings = new \WBAM\Admin\Settings();

		// A crafted POST claiming a slot the site gate does not allow.
		$out = $settings->sanitize_settings(
			array(
				'enabled_placements'    => array( 'header' ),
				'advertiser_placements' => array( 'header', 'popup' ),
			)
		);

		$this->assertSame( array( 'header' ), $out['advertiser_placements'] );
	}

	public function test_sanitizer_defaults_gates_to_empty_arrays(): void {
		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings( array() );

		$this->assertSame( array(), $out['enabled_placements'] );
		$this->assertSame( array(), $out['advertiser_placements'] );
	}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
composer test -- --testsuite free --filter Test_Placement_Gates
```

Expected: FAIL — `Undefined array key "enabled_placements"`.

- [ ] **Step 3: Write minimal implementation**

In `class-settings.php`, add to the `$defaults` array (after `'disable_on_post_types' => array(),`):

```php
		// Placement gates. Empty array means ALL placements — never "none".
		// See Settings_Helper::enabled_placements().
		'enabled_placements'       => array(),
		'advertiser_placements'    => array(),
```

Add to `sanitize_settings()`, immediately before `return $sanitized;`:

```php
		// Placement gates. sanitize_settings() rebuilds the option from
		// scratch, so these must be written explicitly or every save on
		// this screen would wipe them.
		$sanitized['enabled_placements'] = ( ! empty( $input['enabled_placements'] ) && is_array( $input['enabled_placements'] ) )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $input['enabled_placements'] ) ) ) )
			: array();

		$advertiser = ( ! empty( $input['advertiser_placements'] ) && is_array( $input['advertiser_placements'] ) )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $input['advertiser_placements'] ) ) ) )
			: array();

		// A slot closed at site level can never be sellable, whatever the
		// client posted. Enforced server-side; the JS coupling in
		// admin-placement-settings.js is a convenience, not the guard.
		if ( ! empty( $advertiser ) && ! empty( $sanitized['enabled_placements'] ) ) {
			$advertiser = array_values( array_intersect( $advertiser, $sanitized['enabled_placements'] ) );
		}

		$sanitized['advertiser_placements'] = $advertiser;
```

- [ ] **Step 4: Run test to verify it passes**

```bash
composer test -- --testsuite free --filter Test_Placement_Gates
```

Expected: PASS, 12 assertions green.

- [ ] **Step 5: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Admin/class-settings.php tests/free/test-placement-gates.php
git commit -m "feat: persist placement gates through the settings sanitizer"
```

---

## Task 4: Fix the metabox/registry drift

**Files:**
- Modify: `includes/Admin/class-admin.php:920-945`
- Modify: `includes/Core/class-placement-format-map.php:62-96`
- Test: `tests/free/test-placement-registry-parity.php`

**Interfaces:**
- Consumes: `get_selectable_placements()` / `get_selectable_placements_grouped()` from Task 2.
- Produces: nothing new. This task removes duplicated filtering logic.

This is the pre-existing bug from spec §1b: the ad edit metabox reads `get_placements_grouped()` while the advertiser portal reads the `wbam_get_placements` registry. Anything filtering one misses the other.

- [ ] **Step 1: Write the failing test**

Create `tests/free/test-placement-registry-parity.php`:

```php
<?php
/**
 * Regression: the ad edit metabox and the portal registry must always
 * expose the same placement set. They previously read two different
 * lists, so a filter applied to one silently missed the other.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Placements\Placement_Engine;
use WP_UnitTestCase;

class Test_Placement_Registry_Parity extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	/**
	 * @param string[] $gate Site allowlist to apply.
	 * @dataProvider gate_provider
	 */
	public function test_metabox_and_registry_agree( array $gate ): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => $gate ) );

		$engine_ids = array_keys( Placement_Engine::get_instance()->get_selectable_placements() );

		// The registry the advertiser portal and packages read.
		$registry_ids = array_keys( (array) apply_filters( 'wbam_get_placements', array() ) );

		sort( $engine_ids );
		sort( $registry_ids );

		$this->assertSame(
			$engine_ids,
			$registry_ids,
			'Metabox and portal registry exposed different placements.'
		);
	}

	public function gate_provider(): array {
		return array(
			'no gate'     => array( array() ),
			'single slot' => array( array( 'header' ) ),
			'two slots'   => array( array( 'header', 'footer' ) ),
		);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
composer test -- --testsuite free --filter Test_Placement_Registry_Parity
```

Expected: FAIL on the gated cases — the registry still lists every placement because `seed_from_engine()` ignores the allowlist.

- [ ] **Step 3: Write minimal implementation**

In `class-placement-format-map.php`, replace the loop head in `seed_from_engine()` (line ~76):

```php
		// Single source of truth — get_selectable_placements() already
		// applies is_available(), show_in_selector() and the site gate.
		// Duplicating those checks here is what let this registry drift
		// from the ad edit metabox.
		if ( ! method_exists( $engine, 'get_selectable_placements' ) ) {
			return $registry;
		}

		foreach ( $engine->get_selectable_placements() as $slug => $placement ) {
```

and delete the now-redundant guard inside the loop:

```php
			// REMOVE these three lines:
			// if ( ! $placement->is_available() || ! $placement->show_in_selector() ) {
			//     continue;
			// }
			// $slug = $placement->get_id();
```

In `class-admin.php`, replace line ~921:

```php
		$engine     = Placement_Engine::get_instance();
		$all_places = $engine->get_selectable_placements_grouped();
```

and delete the redundant guard inside the inner loop (line ~930):

```php
			// REMOVE:
			// if ( ! $placement->is_available() || ! $placement->show_in_selector() ) {
			//     continue;}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
composer test -- --testsuite free --filter Test_Placement_Registry_Parity
```

Expected: PASS, all three data sets green.

- [ ] **Step 5: Run the full free suite to catch regressions**

```bash
composer test -- --testsuite free
```

Expected: PASS. The metabox change touches ad saving, so `test-cpt.php` and `test-rest-shapes.php` must stay green.

- [ ] **Step 6: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Admin/class-admin.php includes/Core/class-placement-format-map.php tests/free/test-placement-registry-parity.php
git commit -m "fix: metabox and portal registry read one placement list"
```

---

## Task 5: Rendering gate

**Files:**
- Modify: `includes/Modules/Placements/class-placement-engine.php` — `get_ads_for_placement()` line 199
- Test: `tests/free/test-placement-gates.php` (append)

**Interfaces:**
- Consumes: `Settings_Helper::enabled_placements()` from Task 1.
- Produces: nothing new.

Gate at the top of `get_ads_for_placement()` — verified as the single funnel every placement class calls before `render_ad()`. `render_ad()` itself is deliberately NOT gated: it is per-ad and `Shortcode_Placement` calls it with no placement context, so gating there would break `[wbam_ad id="123"]`.

- [ ] **Step 1: Write the failing test**

Append to `tests/free/test-placement-gates.php`, inside the class:

```php
	public function test_closed_placement_returns_no_ads(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer' ) );

		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		// Open: the ad is deliverable.
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'footer' ) ) );
		wp_cache_flush();
		$this->assertNotEmpty( $engine->get_ads_for_placement( 'footer' ) );

		// Closed: delivery stops.
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );
		wp_cache_flush();
		$this->assertSame( array(), $engine->get_ads_for_placement( 'footer' ) );
	}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
composer test -- --testsuite free --filter test_closed_placement_returns_no_ads
```

Expected: FAIL — the closed case still returns the ad ID.

- [ ] **Step 3: Write minimal implementation**

At the very top of `get_ads_for_placement()`, before the cache lookup:

```php
	public function get_ads_for_placement( $placement_id ) {
		// Site gate. A slot the admin has closed delivers nothing, so
		// unticking it in Settings actually stops the ads rather than
		// only hiding the checkbox. Checked before the cache lookup so a
		// warm cache cannot serve a closed slot.
		//
		// The advertiser gate is deliberately NOT applied here: closing a
		// slot for sale must never dark-drop a creative an advertiser has
		// already paid for. See plan/ad-slot-control.md §2.
		$allowed = \WBAM\Core\Settings_Helper::enabled_placements();
		if ( ! empty( $allowed ) && ! in_array( $placement_id, $allowed, true ) ) {
			return array();
		}

		// Try to get cached ad IDs for this placement.
		$cache_key = 'wbam_placement_ads_' . sanitize_key( $placement_id );
```

- [ ] **Step 4: Run test to verify it passes**

```bash
composer test -- --testsuite free --filter test_closed_placement_returns_no_ads
```

Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Modules/Placements/class-placement-engine.php tests/free/test-placement-gates.php
git commit -m "feat: closed placements deliver no ads"
```

---

## Task 6: Active-ad counts

**Files:**
- Create: `includes/Admin/class-placement-settings.php`
- Test: `tests/free/test-placement-gates.php` (append)

**Interfaces:**
- Consumes: `get_selectable_placements()` from Task 2.
- Produces: `Placement_Settings::get_ad_counts(): array<string,int>` — placement ID => count of enabled ads assigned to it. Task 7 renders these.

One query for the whole screen, not one per row (big-site rule 3: no N+1).

- [ ] **Step 1: Write the failing test**

Append to `tests/free/test-placement-gates.php`, inside the class:

```php
	public function test_ad_counts_are_grouped_per_placement(): void {
		$a = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $a, '_wbam_enabled', '1' );
		update_post_meta( $a, '_wbam_placements', array( 'header', 'footer' ) );

		$b = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $b, '_wbam_enabled', '1' );
		update_post_meta( $b, '_wbam_placements', array( 'header' ) );

		// Disabled ads must not be counted.
		$c = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $c, '_wbam_enabled', '0' );
		update_post_meta( $c, '_wbam_placements', array( 'header' ) );

		$counts = \WBAM\Admin\Placement_Settings::get_ad_counts();

		$this->assertSame( 2, $counts['header'] ?? 0 );
		$this->assertSame( 1, $counts['footer'] ?? 0 );
		$this->assertSame( 0, $counts['popup'] ?? 0 );
	}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
composer test -- --testsuite free --filter test_ad_counts_are_grouped_per_placement
```

Expected: FAIL — `Class "WBAM\Admin\Placement_Settings" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `includes/Admin/class-placement-settings.php`:

```php
<?php
/**
 * Placements settings table.
 *
 * Renders the two-gate placement matrix on the Settings screen and counts
 * how many live creatives each slot carries, so an admin closing a slot
 * can see what it costs before they save.
 *
 * @package WB_Ad_Manager
 * @since   2.11.0
 */

namespace WBAM\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Placements settings table.
 */
class Placement_Settings {

	/**
	 * Enabled-ad count per placement ID.
	 *
	 * One grouped pass over the ad meta rather than a query per row — the
	 * settings screen lists every registered placement, and a per-row
	 * query would be an N+1 that grows with every integration module.
	 *
	 * `_wbam_placements` is a serialized array, so it cannot be GROUP BY'd
	 * in SQL. We fetch the enabled ads' meta in one IN() query and tally
	 * in PHP. Ad counts are in the hundreds at most, never the thousands.
	 *
	 * @since 2.11.0
	 * @return array<string,int> Placement ID => enabled ad count.
	 */
	public static function get_ad_counts() {
		global $wpdb;

		$cached = wp_cache_get( 'wbam_placement_ad_counts', 'wbam' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- aggregate for an
		// admin screen, cached below and invalidated on wbam_save_ad_meta.
		$rows = $wpdb->get_col(
			"SELECT pm.meta_value
			   FROM {$wpdb->postmeta} pm
			   JOIN {$wpdb->postmeta} en
			     ON en.post_id = pm.post_id
			    AND en.meta_key = '_wbam_enabled'
			    AND en.meta_value = '1'
			   JOIN {$wpdb->posts} p
			     ON p.ID = pm.post_id
			    AND p.post_type = 'wbam-ad'
			    AND p.post_status != 'trash'
			  WHERE pm.meta_key = '_wbam_placements'"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$counts = array();
		foreach ( (array) $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row ) as $slug ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug ) {
					continue;
				}
				$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
			}
		}

		wp_cache_set( 'wbam_placement_ad_counts', $counts, 'wbam', 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Drop the cached counts when an ad's placements change.
	 *
	 * @since 2.11.0
	 * @return void
	 */
	public static function clear_count_cache() {
		wp_cache_delete( 'wbam_placement_ad_counts', 'wbam' );
	}
}
```

Register the cache invalidation in `includes/Core/class-plugin.php`, next to the existing `wbam_save_ad_meta` wiring:

```php
		add_action( 'wbam_save_ad_meta', array( '\WBAM\Admin\Placement_Settings', 'clear_count_cache' ) );
		add_action( 'delete_post', array( '\WBAM\Admin\Placement_Settings', 'clear_count_cache' ) );
		add_action( 'trashed_post', array( '\WBAM\Admin\Placement_Settings', 'clear_count_cache' ) );
```

- [ ] **Step 4: Run test to verify it passes**

```bash
composer test -- --testsuite free --filter test_ad_counts_are_grouped_per_placement
```

Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Admin/class-placement-settings.php includes/Core/class-plugin.php tests/free/test-placement-gates.php
git commit -m "feat: count live creatives per placement for the settings screen"
```

---

## Task 7: Placements settings section

**Files:**
- Modify: `includes/Admin/class-settings.php` — new section + field callback
- Modify: `includes/Admin/class-placement-settings.php` — add `render_table()`
- Create: `assets/js/admin-placement-settings.js`

**Interfaces:**
- Consumes: `get_selectable_placements_grouped()` (Task 2), `Placement_Settings::get_ad_counts()` (Task 6).
- Produces: the UI. Nothing downstream depends on it.

Note: the table must list **all** registered placements, not just selectable ones — an admin cannot re-open a slot the site gate has closed if the closed slot is not rendered. Use `get_placements_grouped()` here, which is the one legitimate remaining caller.

- [ ] **Step 1: Add the section registration**

In `class-settings.php`, after the existing Display section registration (~line 212):

```php
		add_settings_section(
			'wbam_placements',
			__( 'Placements', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_placements_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'placement_gates',
			__( 'Available Slots', 'wb-ads-rotator-with-split-test' ),
			array( '\WBAM\Admin\Placement_Settings', 'render_table' ),
			'wbam-settings',
			'wbam_placements'
		);
```

and the section description callback, next to `render_display_section()`:

```php
	/**
	 * Placements section description.
	 */
	public function render_placements_section() {
		echo '<p>' . esc_html__(
			'Choose which slots this site uses, and which of those advertisers may buy. Unticking Site stops ads rendering in that slot. Unticking Advertisers only removes it from the advertiser portal — creatives already assigned keep running.',
			'wb-ads-rotator-with-split-test'
		) . '</p>';
	}
```

- [ ] **Step 2: Add the table renderer**

Append to `class-placement-settings.php`:

```php
	/**
	 * Render the two-gate placement matrix.
	 *
	 * Lists EVERY registered placement, not just selectable ones — an
	 * admin must be able to re-open a slot the site gate has closed, and
	 * a closed slot is absent from get_selectable_placements() by design.
	 *
	 * @since 2.11.0
	 * @return void
	 */
	public static function render_table() {
		$engine     = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$counts     = self::get_ad_counts();
		$site       = \WBAM\Core\Settings_Helper::enabled_placements();
		$advertiser = \WBAM\Core\Settings_Helper::advertiser_placements();
		$all_open   = empty( $site );
		$grouped    = $engine->get_placements_grouped();
		$option     = \WBAM\Admin\Settings::OPTION_NAME;
		?>
		<table class="widefat wbam-placement-matrix">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Slot', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Site', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Advertisers', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Active ads', 'wb-ads-rotator-with-split-test' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $grouped as $group => $placements ) : ?>
				<tr class="wbam-placement-matrix__group">
					<th colspan="4" scope="colgroup"><?php echo esc_html( ucfirst( (string) $group ) ); ?></th>
				</tr>
				<?php
				foreach ( $placements as $id => $placement ) :
					if ( ! $placement->show_in_selector() ) {
						continue;
					}
					$count       = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
					$site_on     = $all_open || in_array( $id, $site, true );
					$adv_on      = empty( $advertiser ) || in_array( $id, $advertiser, true );
					$unavailable = ! $placement->is_available();
					?>
					<tr<?php echo $unavailable ? ' class="wbam-placement-matrix__row--unavailable"' : ''; ?>>
						<td>
							<strong><?php echo esc_html( $placement->get_name() ); ?></strong>
							<span class="description"><?php echo esc_html( $placement->get_description() ); ?></span>
							<?php if ( $unavailable ) : ?>
								<em><?php esc_html_e( 'Integration inactive', 'wb-ads-rotator-with-split-test' ); ?></em>
							<?php endif; ?>
						</td>
						<td>
							<label>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: placement name. */
									echo esc_html( sprintf( __( 'Use %s on this site', 'wb-ads-rotator-with-split-test' ), $placement->get_name() ) );
									?>
								</span>
								<input type="checkbox"
									class="wbam-gate-site"
									data-placement="<?php echo esc_attr( $id ); ?>"
									data-count="<?php echo esc_attr( (string) $count ); ?>"
									name="<?php echo esc_attr( $option . '[enabled_placements][]' ); ?>"
									value="<?php echo esc_attr( $id ); ?>"
									<?php checked( $site_on ); ?> />
							</label>
						</td>
						<td>
							<label>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: placement name. */
									echo esc_html( sprintf( __( 'Sell %s to advertisers', 'wb-ads-rotator-with-split-test' ), $placement->get_name() ) );
									?>
								</span>
								<input type="checkbox"
									class="wbam-gate-advertiser"
									data-placement="<?php echo esc_attr( $id ); ?>"
									name="<?php echo esc_attr( $option . '[advertiser_placements][]' ); ?>"
									value="<?php echo esc_attr( $id ); ?>"
									<?php checked( $adv_on ); ?>
									<?php disabled( ! $site_on ); ?> />
							</label>
						</td>
						<td><?php echo esc_html( (string) $count ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
```

- [ ] **Step 3: Add the JS coupling**

Create `assets/js/admin-placement-settings.js`:

```js
/**
 * Placements settings matrix.
 *
 * Two behaviours: an advertiser gate can never outlive its site gate, and
 * closing a slot that carries live creatives asks first. Both are
 * conveniences — sanitize_settings() enforces the intersection server-side.
 */
( function () {
	'use strict';

	var table = document.querySelector( '.wbam-placement-matrix' );
	if ( ! table ) {
		return;
	}

	table.addEventListener( 'change', function ( event ) {
		var box = event.target;
		if ( ! box.classList.contains( 'wbam-gate-site' ) ) {
			return;
		}

		var id = box.getAttribute( 'data-placement' );
		var count = parseInt( box.getAttribute( 'data-count' ), 10 ) || 0;
		var advertiser = table.querySelector(
			'.wbam-gate-advertiser[data-placement="' + id + '"]'
		);

		if ( ! box.checked && count > 0 ) {
			var message = window.wbamPlacementSettings.confirmDisable.replace( '%d', count );
			if ( ! window.confirm( message ) ) {
				box.checked = true;
				return;
			}
		}

		if ( advertiser ) {
			advertiser.disabled = ! box.checked;
			if ( ! box.checked ) {
				advertiser.checked = false;
			}
		}
	} );
}() );
```

Enqueue it on the settings screen in `class-admin.php`, alongside the existing settings assets:

```php
		wp_enqueue_script(
			'wbam-placement-settings',
			WBAM_URL . 'assets/js/admin-placement-settings.js',
			array(),
			WBAM_VERSION,
			true
		);
		wp_localize_script(
			'wbam-placement-settings',
			'wbamPlacementSettings',
			array(
				'confirmDisable' => __(
					'%d active ad(s) will stop rendering in this slot. Continue?',
					'wb-ads-rotator-with-split-test'
				),
			)
		);
```

- [ ] **Step 4: Browser-verify at 1440px and 390px**

Use the Playwright MCP tools. Do NOT write a Playwright script.

1. `browser_navigate` to `<your-local-site>/wp-admin/edit.php?post_type=wbam-ad&page=wbam-settings&autologin=1`

   The settings page lives under the `wbam-ad` CPT menu, NOT `admin.php`. `?autologin=1` uses the dev auto-login mu-plugin — never fill the login form by hand.
2. `browser_take_screenshot` at 1440px — confirm the four columns line up, groups read as headings, counts are correct.
3. `browser_resize` to 390px, screenshot — confirm the table scrolls inside its own container and the page body does not scroll horizontally.
4. Untick a Site box on a slot with a non-zero count — confirm the confirm dialog fires and that cancelling restores the tick.
5. Untick a Site box — confirm the matching Advertisers box disables and clears.
6. Toggle the admin colour scheme to a dark one and re-screenshot.

Save screenshots to `~/Documents/work-artifacts/screenshots/2026-07/`.

- [ ] **Step 5: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Admin/class-settings.php includes/Admin/class-placement-settings.php includes/Admin/class-admin.php assets/js/admin-placement-settings.js
git commit -m "feat: add the Placements settings matrix"
```

---

## Task 7b: Gate the two API consumers

**Files:**
- Modify: `includes/Core/class-abilities.php:1137-1140` — `execute_list_placements()`
- Modify: `includes/API/class-ads-api.php:665-668` — `get_placement_types()`
- Test: `tests/free/test-placement-gates.php` (append)

**Interfaces:**
- Consumes: `get_selectable_placements()` from Task 2.
- Produces: nothing new.

**Why this task exists:** found during Task 4 review, not in the original spec.
Both methods call raw `Placement_Engine::get_placements()` with **no filtering at
all** — not even the `is_available()` / `show_in_selector()` guard the other two
consumers had. So a slot the admin has closed is still listed over the REST API
and the Abilities API. `execute_list_placements` is registered with
`'permission_callback' => '__return_true'` (`class-abilities.php:416`), making it
a public endpoint that leaks the full slot inventory.

This is the same drift class Task 4 fixed, on the two surfaces Task 4 did not
cover. Spec §3.6 lists "API" as a required entry point; this closes it.

- [ ] **Step 1: Write the failing test**

Append to `tests/free/test-placement-gates.php`, inside the class:

```php
	public function test_rest_placement_types_respects_site_gate(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );

		$api      = new \WBAM\API\Ads_API();
		$response = $api->get_placement_types( new \WP_REST_Request( 'GET', '/wbam/v1/placement-types' ) );
		$ids      = wp_list_pluck( $response->get_data()['placements'], 'id' );

		$this->assertContains( 'header', $ids );
		$this->assertNotContains( 'footer', $ids, 'A closed slot must not be listed over REST.' );
		$this->assertNotContains( 'shortcode', $ids, 'A non-selector placement must not be listed over REST.' );
	}

	public function test_abilities_list_placements_respects_site_gate(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );

		$abilities = new \WBAM\Core\Abilities();
		$result    = $abilities->execute_list_placements( array() );
		$ids       = wp_list_pluck( $result['placements'], 'id' );

		$this->assertContains( 'header', $ids );
		$this->assertNotContains( 'footer', $ids, 'A closed slot must not be listed over the Abilities API.' );
	}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite=free --filter "respects_site_gate"
```

Expected: FAIL — both list `footer` because neither method filters.

- [ ] **Step 3: Write minimal implementation**

In `includes/API/class-ads-api.php`, `get_placement_types()`:

```php
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		// Single source of truth — a slot the admin has closed must not be
		// advertised over the API. See plan/ad-slot-control.md §3.1.
		$placements = $engine->get_selectable_placements();
```

In `includes/Core/class-abilities.php`, `execute_list_placements()`:

```php
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		// This ability is registered with permission_callback __return_true,
		// so it is public. Listing closed slots here would leak the site's
		// full inventory to anonymous callers.
		$placements = $engine->get_selectable_placements();
```

Change only the one line in each method. Leave the loop bodies and response
shapes exactly as they are — other consumers depend on them.

- [ ] **Step 4: Run test to verify it passes**

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite=free
```

Expected: both new tests PASS; suite still shows exactly 3 failures / 1 skip.

- [ ] **Step 5: Lint and commit**

```bash
composer lint-fix && composer lint
git add includes/Core/class-abilities.php includes/API/class-ads-api.php tests/free/test-placement-gates.php
git commit -m "fix: gate REST and Abilities placement listings behind the site allowlist"
```

---

## Task 8: Ship the branch

**Files:** none — verification and PR.

- [ ] **Step 1: Full local gate**

```bash
composer lint-fix
composer verify-no-test
```

Expected: PASS — lint, phpstan, arch-checks, plugincheck, verify-flow all green. Fix anything red before continuing; do not push on a red gate.

- [ ] **Step 2: Full test suite**

```bash
composer test -- --testsuite free
```

Expected: PASS, including the pre-existing tests. Report any pre-existing failures honestly rather than silencing them.

- [ ] **Step 3: Upgrade-safety check on a real install**

```bash
wp option get wbam_settings --format=json
```

Confirm `enabled_placements` and `advertiser_placements` are absent or empty on an install that has never opened the new screen, and that ads still render on the front end. Empty means all — this is the guarantee that existing sites are unaffected.

- [ ] **Step 4: Save the Settings screen once, then re-check**

Visit Settings, save without changing anything, then:

```bash
wp option get wbam_settings --format=json
```

Confirm both keys survived and no unrelated key was dropped. This is the `sanitize_settings()` rebuild trap from Global Constraints.

- [ ] **Step 5: Push and open the PR**

```bash
git push -u origin feature/ad-slot-control
gh pr create --base main --title "Ad slot control: two-level placement gates" --body "$(cat <<'EOF'
## Summary

Adds a two-level control over placement slots: a **site** gate (which slots this site uses) and an **advertiser** gate (which of those are sellable). Both live in `wbam_settings`; empty means all, so existing installs are unaffected.

Also fixes a pre-existing drift bug: the ad edit metabox and the advertiser portal registry read two different placement lists, so filtering one silently missed the other. Both now read `Placement_Engine::get_selectable_placements()`.

## Behaviour

- Site gate stops delivery — gated in `get_ads_for_placement()`, the single funnel every placement uses.
- Advertiser gate is selection-only, so closing a slot for sale never dark-drops a creative an advertiser already paid for.
- Advertiser list is always intersected with the site list server-side.
- Closing a slot with live creatives shows the count and asks first.

## Design

See `plan/ad-slot-control.md` and `plan/ad-slot-control-free-implementation.md`.

## Test plan

- `composer verify-no-test` green
- `composer test -- --testsuite free` green
- Settings screen browser-verified at 1440px and 390px, light and dark
- Upgrade check: untouched install has empty gates and unchanged front-end rendering
EOF
)"
```

- [ ] **Step 6: Merge when green**

```bash
gh pr checks --watch
gh pr merge --squash --delete-branch
```

---

## Interface contract for downstream plans

Locked here so follow-on plans cannot drift. The Pro plugin and any
site-integration plugin consume these and nothing else:

```php
// Gate resolution — the ONLY sanctioned way to read either gate.
\WBAM\Core\Settings_Helper::enabled_placements(): string[]      // empty = all
\WBAM\Core\Settings_Helper::advertiser_placements(): string[]   // empty = all

// Placement listing — the ONLY sanctioned way to build a placement list.
\WBAM\Modules\Placements\Placement_Engine::get_selectable_placements(): Placement_Interface[]
\WBAM\Modules\Placements\Placement_Engine::get_selectable_placements_grouped(): array<string, Placement_Interface[]>

// Filters.
apply_filters( 'wbam_enabled_placements', string[] $ids ): string[]
apply_filters( 'wbam_advertiser_placements', string[] $ids ): string[]

// Registration (unchanged, already exists) — how integrations and Pro custom slots register.
do_action( 'wbam_register_placements', Placement_Engine $engine )
$engine->register_placement( Placement_Interface $placement )
```

**Pro plan** hooks `Settings_Helper::advertiser_placements()` onto the existing
`wbam_pro_selectable_placements` filter, and adds the Custom Slots module.

**Site-integration plans** register `Placement_Interface` implementations on
`wbam_register_placements`, migrate any legacy hardcoded ad-ID option onto
`_wbam_placements`, and may seed both gate keys on activation. Those live with
the client's site records, not in this repo.

---

## Self-review

**Spec coverage**

| Spec section | Task |
|---|---|
| §3.1 single source of truth | 2 |
| §3.2 two settings + filters | 1, 3 |
| §3.3 settings UI, counts, confirm, intersection | 6, 7 |
| §3.4 rendering gate | 5 |
| §3.5 big-site readiness (no N+1, memoised, no new table) | 6 |
| §3.6 free entry points | 3 (API via existing `Settings_API` reading `wbam_settings`) |
| §1b drift bug | 4 |
| §8 upgrade safety | 8 steps 3–4 |

§4 (Pro) is deliberately out of scope — a separate plan, per the scope-check
rule. Each plan produces working software on its own.

**Type consistency** — `enabled_placements()` / `advertiser_placements()` return
`string[]` everywhere; `get_selectable_placements()` returns
`Placement_Interface[]` keyed by ID in every caller; `get_ad_counts()` returns
`array<string,int>` in both its definition and its consumer.

**Known gap, deliberate:** `Placement_Settings::render_table()` calls
`get_placements_grouped()` rather than the new method, because a closed slot must
still render so it can be re-opened. That is the one legitimate remaining caller
and it is commented as such in the code.
