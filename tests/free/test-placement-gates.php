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
}
