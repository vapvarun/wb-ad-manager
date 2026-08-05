<?php
/**
 * Shortcode-placed ads respect the full delivery gate set.
 *
 * Regression guard for Basecamp card 10166012108: render_ad() gated
 * only on _wbam_enabled and the per-page cap, so [wbam_ad id="X"]
 * served expired, not-yet-started, and impression-capped creatives
 * that placement selection correctly withheld. The full
 * Targeting_Engine::should_display() gate now runs on every frontend
 * render path.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

class Test_Shortcode_Delivery_Gates extends \WP_UnitTestCase {

	private function make_ad( string $title, array $meta = array() ): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type' => 'code',
				'code' => '<span class="gate-guard-body">' . $title . '</span>',
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $ad_id, $key, $value );
		}

		return $ad_id;
	}

	public function test_expired_ad_is_withheld_from_the_shortcode(): void {
		$ad_id = $this->make_ad(
			'Gate expired',
			array(
				'_wbam_start_date' => '2020-01-01',
				'_wbam_end_date'   => '2020-12-31',
			)
		);

		$this->assertSame( '', do_shortcode( '[wbam_ad id="' . $ad_id . '"]' ), 'An end date that stops the ad in a slot must stop it in a shortcode too.' );
	}

	public function test_future_ad_is_withheld_from_the_shortcode(): void {
		$ad_id = $this->make_ad( 'Gate future', array( '_wbam_start_date' => '2099-01-01' ) );

		$this->assertSame( '', do_shortcode( '[wbam_ad id="' . $ad_id . '"]' ) );
	}

	public function test_impression_capped_ad_is_withheld_from_the_shortcode(): void {
		$ad_id = $this->make_ad(
			'Gate capped',
			array(
				'_wbam_impression_cap'   => 5,
				'_wbam_impression_count' => 999,
			)
		);

		$this->assertSame( '', do_shortcode( '[wbam_ad id="' . $ad_id . '"]' ) );
	}

	public function test_live_ad_still_renders_through_the_shortcode(): void {
		$ad_id = $this->make_ad( 'Gate live' );

		$this->assertStringContainsString( 'gate-guard-body', do_shortcode( '[wbam_ad id="' . $ad_id . '"]' ) );
	}

	public function test_should_display_filter_reaches_the_shortcode_path(): void {
		$ad_id = $this->make_ad( 'Gate filtered' );

		add_filter( 'wbam_should_display_ad', '__return_false' );
		$output = do_shortcode( '[wbam_ad id="' . $ad_id . '"]' );
		remove_filter( 'wbam_should_display_ad', '__return_false' );

		$this->assertSame( '', $output, 'The extension filter Pro and site owners hang delivery logic on must gate by-id renders too.' );
	}
}
