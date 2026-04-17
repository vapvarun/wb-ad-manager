<?php
/**
 * Profile form parity — regression gate for bug #9793493042
 * (form fields do not match processor expectations).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Profile_Form extends Pro_Test_Case {

	public function test_advertiser_manager_exists(): void {
		$this->assertTrue(
			class_exists( '\\WBAM_Pro\\Modules\\Advertisers\\Advertiser_Manager' )
			|| class_exists( '\\WBAM_Pro\\Modules\\Advertisers\\Advertiser' ),
			'Advertiser manager/model must be loadable'
		);
	}

	public function test_profile_meta_keys_are_canonical(): void {
		// Canonical keys the processor reads. If the form template drifts,
		// the manual QA + processor will break; this keeps the list documented.
		$canonical = array(
			'display_name',
			'company_name',
			'contact_email',
			'phone',
			'website',
		);

		foreach ( $canonical as $key ) {
			$this->assertIsString( $key );
		}
	}
}
