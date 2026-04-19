<?php
/**
 * CPT registration contract.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;

class Test_CPT extends WP_UnitTestCase {

	public function test_wbam_ad_registered_with_rest_support(): void {
		$cpt = get_post_type_object( 'wbam-ad' );
		$this->assertNotNull( $cpt );
		$this->assertTrue( $cpt->show_in_rest, 'wbam-ad must be exposed via REST' );
	}

	public function test_ad_supports_title_and_content(): void {
		$this->assertTrue( post_type_supports( 'wbam-ad', 'title' ) );
		$this->assertTrue( post_type_supports( 'wbam-ad', 'editor' ) );
	}

	public function test_ad_is_public_or_ui_only(): void {
		$cpt = get_post_type_object( 'wbam-ad' );
		$this->assertIsBool( $cpt->public );
	}
}
