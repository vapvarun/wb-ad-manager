<?php
/**
 * A3 audit fix: the Placements panel hides/disables itself for ad types
 * that don't use placements (video ads, delivered in-stream). This must
 * not become a data-loss bug: saving an ad while its type is in that list
 * must leave `_wbam_placements` untouched, because a hidden panel's
 * checkboxes are never posted (whether they render `disabled` or not),
 * and the save handler's "union posted with whatever the form didn't
 * offer" logic (see Admin::save_meta()) has no notion of "not offered
 * because of this ad's type" - only "not offered because the site gate /
 * an integration closed it".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_Ad_Type_Panels extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'wbam_ad_types_without_placements' );
		parent::tear_down();
	}

	/**
	 * Regression guard for the exact footgun this fix could have
	 * introduced: rendering the hidden Placements checkboxes as
	 * `disabled` would have made the browser omit them from the POST,
	 * and get_selectable_placements() (which save_meta() treats as "the
	 * placements the form could have offered") does not exclude a slug
	 * just because the CURRENT ad is a placements-less type - so a save
	 * would have silently wiped `_wbam_placements` to empty.
	 */
	public function test_saving_a_placementsless_ad_type_does_not_wipe_stored_placements(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer', 'header' ) );
		update_post_meta( $ad_id, '_wbam_ad_data', array( 'type' => 'video' ) );

		// A type this build doesn't know about, forced in via the filter -
		// exercises the mechanism without depending on Pro's Video_Ad class
		// being installed.
		add_filter(
			'wbam_ad_types_without_placements',
			static function () {
				return array( 'video' );
			}
		);

		$original = $_POST;
		// No 'wbam_placements' key at all - exactly what a real submission
		// looks like when that metabox's checkboxes are hidden, since a
		// hidden (non-disabled) checkbox still posts its checked state, but
		// this simulates the type being switched to video via the tab
		// click before any box was ever ticked in this session.
		$_POST = array(
			'wbam_nonce' => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_data'  => array( 'type' => 'video' ),
		);

		\WBAM\Admin\Admin::get_instance()->save_meta( $ad_id, get_post( $ad_id ) );

		$_POST = $original;

		$saved = get_post_meta( $ad_id, '_wbam_placements', true );

		$this->assertContains( 'footer', (array) $saved, 'Existing placement assignment must survive a save while the ad type has no Placements UI.' );
		$this->assertContains( 'header', (array) $saved, 'Existing placement assignment must survive a save while the ad type has no Placements UI.' );
	}

	public function test_default_placementsless_types_include_video(): void {
		$reflection = new \ReflectionMethod( '\WBAM\Admin\Admin', 'ad_types_without_placements' );
		$reflection->setAccessible( true );

		$this->assertContains( 'video', $reflection->invoke( null ) );
	}

	public function test_default_no_sizing_types_include_video(): void {
		$reflection = new \ReflectionMethod( '\WBAM\Admin\Admin', 'ad_types_without_sizing' );
		$reflection->setAccessible( true );

		$this->assertContains( 'video', $reflection->invoke( null ) );
	}

	public function test_ad_types_without_placements_filter_is_honoured(): void {
		add_filter(
			'wbam_ad_types_without_placements',
			static function ( $types ) {
				$types[] = 'custom_type';
				return $types;
			}
		);

		$reflection = new \ReflectionMethod( '\WBAM\Admin\Admin', 'ad_types_without_placements' );
		$reflection->setAccessible( true );

		$this->assertContains( 'custom_type', $reflection->invoke( null ) );
	}
}
