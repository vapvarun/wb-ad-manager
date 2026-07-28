<?php
/**
 * CPT registration contract.
 *
 * These previously asserted that wbam-ad supports the block editor and is
 * exposed on /wp/v2. It does neither, on purpose, so both assertions failed
 * from the day they were written and the suite has been red ever since.
 * Rewritten to lock in the contract the plugin actually intends, so the tests
 * guard those decisions instead of contradicting them.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_CPT extends WP_UnitTestCase {

	public function test_wbam_ad_is_registered(): void {
		$this->assertNotNull( get_post_type_object( 'wbam-ad' ) );
		$this->assertTrue( post_type_exists( 'wbam-ad' ) );
	}

	/**
	 * The CPT is deliberately NOT on /wp/v2.
	 *
	 * An ad's payload lives in meta and is served by the plugin's own /wbam/v1
	 * routes, which filter by enabled state and placement. Turning show_in_rest
	 * on would publish the raw post - including ads that are disabled or
	 * scheduled - through a route with none of that filtering.
	 */
	public function test_wbam_ad_is_not_exposed_on_core_rest(): void {
		$cpt = get_post_type_object( 'wbam-ad' );

		$this->assertFalse(
			(bool) $cpt->show_in_rest,
			'wbam-ad must stay off /wp/v2 - the plugin serves ads through /wbam/v1, which filters by enabled state.'
		);
	}

	/**
	 * Title only, deliberately.
	 *
	 * The ad body is built from the ad-type panels (image, code, rich content),
	 * not post_content. Adding 'editor' support would put a content editor on
	 * the screen that writes to a field nothing renders.
	 */
	public function test_wbam_ad_supports_title_but_not_the_post_editor(): void {
		$this->assertTrue(
			post_type_supports( 'wbam-ad', 'title' ),
			'The ad title is the admin-facing name and must stay supported.'
		);

		$this->assertFalse(
			post_type_supports( 'wbam-ad', 'editor' ),
			'wbam-ad must not support the post editor - ad content comes from the ad-type panels, not post_content.'
		);
	}

	/**
	 * Not publicly queryable: an ad has no canonical URL of its own.
	 */
	public function test_wbam_ad_is_admin_only(): void {
		$cpt = get_post_type_object( 'wbam-ad' );

		$this->assertFalse( (bool) $cpt->public );
		$this->assertTrue( (bool) $cpt->show_ui, 'The CPT is admin-managed, so its UI must be available.' );
	}
}
