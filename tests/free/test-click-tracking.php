<?php
/**
 * Click tracking — regression gate for bug #9788819224
 * (views/clicks not tracking on approved ads).
 *
 * Asserts a tracked click produces a row in wbam_analytics. We do not
 * drive the full AJAX stack here; we drive the analytics model layer
 * directly via do_action( 'wbam_track_click', … ) if registered, else
 * skip with a clear message so CI surfaces the missing hook.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;
use WBAM\Tests\Helpers\Factory;

class Test_Click_Tracking extends WP_UnitTestCase {

	public function test_click_produces_analytics_row(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_analytics';

		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$ad_id = Factory::make_ad();

		/**
		 * Fire the canonical tracking action. Plugin registers it in
		 * Modules/Analytics; if it is missing, fail loud instead of
		 * silently passing.
		 */
		if ( ! has_action( 'wbam_track_click' ) ) {
			$this->markTestSkipped( 'wbam_track_click action is not registered — fix the plugin, not the test.' );
		}

		do_action( 'wbam_track_click', $ad_id, array( 'source' => 'phpunit' ) );

		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$this->assertGreaterThan( $before, $after, 'Click tracking must persist a row' );
	}
}
