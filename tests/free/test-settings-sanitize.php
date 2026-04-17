<?php
/**
 * Settings round-trip: save-then-read should not lose or corrupt data.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Settings_Sanitize extends WP_UnitTestCase {

	public function test_settings_option_round_trips_scalars(): void {
		$payload = array(
			'enabled'   => true,
			'threshold' => 42,
			'label'     => 'Hello',
		);

		update_option( 'wbam_settings', $payload );
		$stored = get_option( 'wbam_settings' );

		$this->assertIsArray( $stored );
		$this->assertSame( $payload['threshold'], (int) $stored['threshold'] );
		$this->assertSame( $payload['label'], (string) $stored['label'] );
	}

	public function test_unknown_settings_key_is_tolerated(): void {
		update_option( 'wbam_settings', array( 'mystery' => 'value' ) );
		$this->assertIsArray( get_option( 'wbam_settings' ) );
	}
}
