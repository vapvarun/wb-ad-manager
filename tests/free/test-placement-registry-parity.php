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
