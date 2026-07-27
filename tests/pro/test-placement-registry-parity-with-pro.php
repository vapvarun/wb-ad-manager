<?php
/**
 * Regression: the placement registry must not widen when PRO is active.
 *
 * tests/free/test-placement-registry-parity.php already asserts that the ad
 * edit metabox and the `wbam_get_placements` registry agree — but it runs
 * with WBAM_RUN_PRO_TESTS=0, so PRO's own callbacks are never registered.
 * It therefore passed while a live site with PRO installed drifted.
 *
 * PRO's Ad_Submission_Shortcodes::get_available_placements() hooks the same
 * filter at priority 10, AFTER Placement_Format_Map::seed_from_engine() at
 * 5. It used to call get_placements() (unfiltered) and re-implement the
 * is_available()/show_in_selector() guard by hand, so whatever the site
 * allowlist had closed was silently added back — and, because it runs later,
 * its version won.
 *
 * The consequence reached the admin: the ad edit screen's "Will render in:"
 * summary reads this registry, so it listed placements that were switched
 * off site-wide, telling the site owner an ad would appear where it
 * structurally could not.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;

class Test_Placement_Registry_Parity_With_Pro extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	/**
	 * @param string[] $gate Site allowlist to apply.
	 * @dataProvider gate_provider
	 */
	public function test_registry_never_widens_past_the_site_gate( array $gate ): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => $gate ) );

		$engine_ids   = array_keys( Placement_Engine::get_instance()->get_selectable_placements() );
		$registry_ids = array_keys( (array) apply_filters( 'wbam_get_placements', array() ) );

		sort( $engine_ids );
		sort( $registry_ids );

		$this->assertSame(
			$engine_ids,
			$registry_ids,
			'With PRO active the registry exposed placements the site gate had closed.'
		);
	}

	public function gate_provider(): array {
		return array(
			'no gate'     => array( array() ),
			'single slot' => array( array( 'header' ) ),
			'two slots'   => array( array( 'header', 'footer' ) ),
		);
	}

	/**
	 * The specific shape of the original bug: a closed placement must not
	 * reappear via a later-priority callback.
	 */
	public function test_closed_placement_absent_from_registry(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );

		$registry_ids = array_keys( (array) apply_filters( 'wbam_get_placements', array() ) );

		$this->assertContains( 'header', $registry_ids );
		$this->assertNotContains( 'footer', $registry_ids, 'A closed slot was re-added by a PRO callback.' );
		$this->assertNotContains( 'popup', $registry_ids, 'A closed slot was re-added by a PRO callback.' );
	}
}
